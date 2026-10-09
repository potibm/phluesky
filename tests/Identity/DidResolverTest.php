<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Identity;

use Http\Discovery\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Exception\HttpRequestException;
use potibm\Bluesky\Exception\HttpStatusCodeException;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\HttpComponentsManager;
use potibm\Bluesky\Identity\DidDocument;
use potibm\Bluesky\Identity\DidResolver;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(DidResolver::class)]
#[UsesClass(DidDocument::class)]
#[UsesClass(HttpComponentsManager::class)]
final class DidResolverTest extends TestCase
{
    /**
     * @var list<RequestInterface>
     */
    private array $requests = [];

    public function testResolvesPdsDidFromPlcDirectory(): void
    {
        $resolver = $this->createResolver(200, self::didDocument('https://morel.us-east.host.bsky.network'));

        $result = $resolver->resolvePdsDid('did:plc:abc123');

        $this->assertEquals('did:web:morel.us-east.host.bsky.network', $result);
        $this->assertStringContainsString('plc.directory/did:plc:abc123', (string) $this->requests[0]->getUri());
    }

    public function testResolvesPdsDidFromDidWeb(): void
    {
        $resolver = $this->createResolver(200, self::didDocument('https://pds.example.com'));

        $result = $resolver->resolvePdsDid('did:web:example.com');

        $this->assertEquals('did:web:pds.example.com', $result);
        $this->assertStringContainsString('https://example.com/.well-known/did.json', (string) $this->requests[0]->getUri());
    }

    public function testResolvesPdsDidFromDidWebWithPath(): void
    {
        $resolver = $this->createResolver(200, self::didDocument('https://pds.example.com'));

        $resolver->resolvePdsDid('did:web:example.com:path:sub');

        $this->assertStringContainsString('https://example.com/path/sub/did.json', (string) $this->requests[0]->getUri());
    }

    public function testResolvesPdsDidFromDidWebWithPort(): void
    {
        $resolver = $this->createResolver(200, self::didDocument('https://pds.example.com:3000'));

        $result = $resolver->resolvePdsDid('did:web:example.com%3A3000');

        $this->assertEquals('did:web:pds.example.com%3A3000', $result);
        $this->assertStringContainsString('https://example.com:3000/.well-known/did.json', (string) $this->requests[0]->getUri());
    }

    public function testThrowsWhenPdsServiceIsMissing(): void
    {
        $document = new \stdClass();
        $document->id = 'did:plc:abc123';
        $document->service = [];

        $resolver = $this->createResolver(200, $document);

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('atproto PDS service');
        $resolver->resolvePdsDid('did:plc:abc123');
    }

    public function testThrowsForUnsupportedDidMethod(): void
    {
        $resolver = $this->createResolver(200, self::didDocument('https://pds.example.com'));

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Unsupported DID method');
        $resolver->resolvePdsDid('did:key:abc123');
    }

    public function testThrowsOnHttpError(): void
    {
        $resolver = $this->createResolver(404, 'not found');

        $this->expectException(HttpStatusCodeException::class);
        $resolver->resolvePdsDid('did:plc:abc123');
    }

    public function testThrowsOnInvalidJson(): void
    {
        $resolver = $this->createResolver(200, 'not-json');

        $this->expectException(InvalidPayloadException::class);
        $resolver->resolvePdsDid('did:plc:abc123');
    }

    public function testThrowsOnHttpRequestFailure(): void
    {
        $psr17Factory = new Psr17Factory();
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('sendRequest')->willThrowException(
            $this->createMock(ClientExceptionInterface::class)
        );

        $resolver = new DidResolver(new HttpComponentsManager($httpClient, $psr17Factory, $psr17Factory, $psr17Factory));

        $this->expectException(HttpRequestException::class);
        $resolver->resolvePdsDid('did:plc:abc123');
    }

    private function createResolver(int $statusCode, array|string|\stdClass $body): DidResolver
    {
        $psr17Factory = new Psr17Factory();

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        /** @psalm-suppress PossiblyFalseArgument */
        $response->method('getBody')->willReturn($psr17Factory->createStream(
            is_string($body) ? $body : json_encode($body)
        ));

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use ($response): ResponseInterface {
                $this->requests[] = $request;

                return $response;
            }
        );

        return new DidResolver(new HttpComponentsManager($httpClient, $psr17Factory, $psr17Factory, $psr17Factory));
    }

    private static function didDocument(string $endpoint): \stdClass
    {
        $service = new \stdClass();
        $service->id = '#atproto_pds';
        $service->type = 'AtprotoPersonalDataServer';
        $service->serviceEndpoint = $endpoint;

        $document = new \stdClass();
        $document->id = 'did:plc:abc123';
        $document->service = [$service];

        return $document;
    }
}
