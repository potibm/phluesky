<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test;

use Http\Discovery\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\BlueskyApi;
use potibm\Bluesky\BlueskyUri;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Exception\AuthenticationErrorException;
use potibm\Bluesky\Exception\HttpRequestException;
use potibm\Bluesky\Exception\HttpStatusCodeException;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\HttpComponentsManager;
use potibm\Bluesky\Response\CreateSessionResponse;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Test\Response\RecordResponseTest;
use potibm\Bluesky\Test\Response\UploadBlobResponseTest;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;

#[CoversClass(BlueskyApi::class)]
#[CoversClass(HttpComponentsManager::class)]
#[UsesClass(Post::class)]
#[UsesClass(RecordResponse::class)]
#[UsesClass(CreateSessionResponse::class)]
#[UsesClass(Images::class)]
#[UsesClass(UploadBlobResponse::class)]
#[UsesClass(BlueskyUri::class)]
final class BlueskyApiTest extends TestCase
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

    public function testAuthenticationErrorOnCreateRecord(): void
    {
        $this->expectException(AuthenticationErrorException::class);

        $post = Post::create('Test for a post');

        $httpComponent = $this->generateHttpComponentsManager(401, true, [
            'error' => 'AuthenticationRequired',
            'message' => 'Invalid identifier or password',
        ]);
        $api = new BlueskyApi('identifier', 'wrongpassword', $httpComponent);

        $api->createRecord($post);
    }

    public function testFailsOnInvalidJsonPayloadOnCreateRecord(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Failed to encode body to JSON');

        $httpComponent = $this->generateHttpComponentsManager(200, true, [
            'accessJwt' => 'accessJwt',
            'did' => 'did:bluesky:1234567890',
        ], [
            'uri' => 'my-uri',
            'cid' => 'cid:1234567890',
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $loop = new \stdClass();
        $loop->self = $loop;

        $badFacet = $this->createMock(\potibm\Bluesky\Richtext\AbstractFacet::class);
        $badFacet->method('jsonSerialize')->willReturn($loop); // rekursiv → json_encode schlägt fehl

        $post = new Post();
        $post->setText('text');
        $post->addFacet($badFacet);

        $api->createRecord($post);
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
        $this->assertInstanceOf(RecordResponse::class, $response);
        $this->assertEquals('my-uri', $response->getUri()->getUri());
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

    public function testGetRecord(): void
    {
        $httpComponent = $this->generateHttpComponentsManager(200, true, RecordResponseTest::generateBlobResponse());

        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $response = $api->getRecord(new BlueskyUri('at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k4duaz5vfs2b'));

        $this->assertInstanceOf(RecordResponse::class, $response);
    }

    private function generateHttpComponentsManager(int $statusCode, bool $jsonEncode, array|string|\stdClass ...$bodies): HttpComponentsManager
    {
        $psr17Factory = new Psr17Factory();

        $responses = [];
        foreach ($bodies as $body) {
            if ($jsonEncode || ! is_string($body)) {
                $body = json_encode($body);
            }
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn($statusCode);
            /** @psalm-suppress PossiblyFalseArgument */
            $stream = $psr17Factory->createStream($body);
            $response->method('getBody')->willReturn($stream);
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
