<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test;

use Http\Discovery\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\BlueskyApi;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Exception\HttpRequestException;
use potibm\Bluesky\Exception\HttpStatusCodeException;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\HttpComponentsManager;
use potibm\Bluesky\Response\CreateRecordResponse;
use potibm\Bluesky\Response\CreateSessionResponse;
use potibm\Bluesky\Response\ResponseTrait;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Test\Response\UploadBlobResponseTest;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;

#[CoversClass(BlueskyApi::class)]
#[CoversClass(HttpComponentsManager::class)]
#[UsesClass(Post::class)]
#[UsesClass(CreateRecordResponse::class)]
#[UsesClass(CreateSessionResponse::class)]
#[UsesClass(ResponseTrait::class)]
#[UsesClass(Images::class)]
#[UsesClass(UploadBlobResponse::class)]
class BlueskyApiTest extends TestCase
{
    public function testGetDidForHandle(): void
    {
        $httpComponent = $this->generateHttpComponentsManager(200, true, [
            'did' => 'did:bluesky:1234567890',
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $this->assertEquals('did:bluesky:1234567890', $api->getDidForHandle('handle'));
    }

    public function testGetDidForHandleExceptionOnRequest(): void
    {
        $this->expectException(HttpRequestException::class);

        $httpComponent = $this->generateHttpComponentsManager(200, true, []);
        $exception = $this->createMock(ClientExceptionInterface::class);
        /** @psalm-suppress UndefinedInterfaceMethod */
        $httpComponent->httpClient->method("sendRequest")->willThrowException($exception);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);
        $api->getDidForHandle('handle');
    }

    public function testGetDidForHandle404(): void
    {
        $this->expectException(HttpStatusCodeException::class);

        $httpComponent = $this->generateHttpComponentsManager(404, true, [
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);
        $api->getDidForHandle('handle');
    }

    public function testGetDidForHandleInvalidPayload(): void
    {
        $this->expectException(InvalidPayloadException::class);

        $httpComponent = $this->generateHttpComponentsManager(200, false, '[');
        $api = new BlueskyApi('identifier', 'password', $httpComponent);
        $api->getDidForHandle('handle');
    }

    public function testGetDidForHandleMissingValue(): void
    {
        $this->expectException(InvalidPayloadException::class);

        $httpComponent = $this->generateHttpComponentsManager(200, true, [
            "withoutdid" => "value",
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);
        $api->getDidForHandle('handle');
    }

    public function testCreateRecord(): void
    {
        $post = Post::create('Test for a post');

        $httpComponent = $this->generateHttpComponentsManager(200, true, [
            'accessJwt' => 'accessJwt',
            'did' => 'did:bluesky:1234567890',
        ], [
            'uri' => 'my-uri',
            'cid' => 'cid:1234567890',
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $response = $api->createRecord($post);
        $this->assertInstanceOf(CreateRecordResponse::class, $response);
        $this->assertEquals('my-uri', $response->getUri());
        $this->assertEquals('cid:1234567890', $response->getCid());
    }

    public function testUploadBlob(): void
    {
        $httpComponent = $this->generateHttpComponentsManager(200, true, [
            'accessJwt' => 'accessJwt',
            'did' => 'did:bluesky:1234567890',
        ], [
            'blob' => (array) UploadBlobResponseTest::generateBlobResponse(),
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $response = $api->uploadBlob('imagecontent', 'image/jpeg');
        $this->assertInstanceOf(UploadBlobResponse::class, $response);
        $this->assertEquals('image/jpeg', $response->getMimeType());
        $this->assertEquals(123, $response->getSize());
        $this->assertEquals('https://example.com', $response->getRefLink());
    }

    public function testUploadBloWithMissingBlobPropertyInResponse(): void
    {
        $httpComponent = $this->generateHttpComponentsManager(200, true, [
            'accessJwt' => 'accessJwt',
            'did' => 'did:bluesky:1234567890',
        ], [
            'key' => 'value',
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $this->expectException(InvalidPayloadException::class);
        $api->uploadBlob('imagecontent', 'image/jpeg');
    }

    private function generateHttpComponentsManager(int $statusCode, bool $jsonEncode, mixed ...$bodies): HttpComponentsManager
    {
        $psr17Factory = new Psr17Factory();

        $responses = [];
        foreach ($bodies as $body) {
            if ($jsonEncode) {
                $body = json_encode($body);
            }
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn($statusCode);
            $response->method('getBody')->willReturn(
                $psr17Factory->createStream($body)
            );
            $responses[] = $response;
        }

        $httpClient = $this->createMock(ClientInterface::class);
        call_user_func_array([$httpClient->method('sendRequest'), "willReturn"], $responses);

        return new HttpComponentsManager(
            $httpClient,
            $this->createMock(UriFactoryInterface::class),
            $psr17Factory,
            $psr17Factory
        );
    }
}
