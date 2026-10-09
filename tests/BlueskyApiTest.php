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
use potibm\Bluesky\Identity\DidDocument;
use potibm\Bluesky\Response\CreateSessionResponse;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Response\VideoJobStatusResponse;
use potibm\Bluesky\Test\Response\RecordResponseTest;
use potibm\Bluesky\Test\Response\UploadBlobResponseTest;
use potibm\Bluesky\Test\Response\VideoJobStatusResponseTest;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

#[CoversClass(BlueskyApi::class)]
#[CoversClass(HttpComponentsManager::class)]
#[UsesClass(Post::class)]
#[UsesClass(RecordResponse::class)]
#[UsesClass(CreateSessionResponse::class)]
#[UsesClass(Images::class)]
#[UsesClass(UploadBlobResponse::class)]
#[UsesClass(BlueskyUri::class)]
#[UsesClass(VideoJobStatusResponse::class)]
#[UsesClass(DidDocument::class)]
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

        $httpComponent = $this->generateHttpComponentsManager(404, true, []);
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

    public function testGetServiceAuth(): void
    {
        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'accessJwt',
                    'did' => 'did:plc:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => self::generateDidDocument(),
            ],
            [
                'status' => 200,
                'body' => [
                    'token' => 'service-token',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $this->assertEquals('service-token', $api->getServiceAuth('com.atproto.repo.uploadBlob'));
    }

    public function testGetServiceAuthUsesPdsDidAsAudience(): void
    {
        $psr17Factory = new Psr17Factory();
        $requests = [];

        $responseSpecs = [
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'accessJwt',
                    'did' => 'did:plc:user',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => self::generateDidDocument(),
            ],
            [
                'status' => 200,
                'body' => [
                    'token' => 'service-token',
                ],
            ],
        ];

        $responseMocks = [];
        foreach ($responseSpecs as $spec) {
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn($spec['status']);
            /** @psalm-suppress PossiblyFalseArgument */
            $response->method('getBody')->willReturn($psr17Factory->createStream(json_encode($spec['body'])));
            $responseMocks[] = $response;
        }

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use (&$requests, &$responseMocks): ResponseInterface {
                $requests[] = $request;

                $response = array_shift($responseMocks);
                if (! $response instanceof ResponseInterface) {
                    throw new \RuntimeException('No more mocked responses available');
                }

                return $response;
            }
        );

        $httpComponent = new HttpComponentsManager($httpClient, $psr17Factory, $psr17Factory, $psr17Factory);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $api->getServiceAuth('com.atproto.repo.uploadBlob');

        $this->assertStringContainsString(
            'plc.directory/did:plc:user',
            (string) $requests[1]->getUri()
        );
        $this->assertStringContainsString(
            'aud=' . rawurlencode('did:web:morel.us-east.host.bsky.network'),
            (string) $requests[2]->getUri()
        );
    }

    public function testGetServiceAuthMissingPdsService(): void
    {
        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'accessJwt',
                    'did' => 'did:plc:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'id' => 'did:plc:1234567890',
                    'service' => [],
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $this->expectException(InvalidPayloadException::class);
        $api->getServiceAuth('com.atproto.repo.uploadBlob');
    }

    public function testGetServiceAuthWithCustomAudience(): void
    {
        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'accessJwt',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'token' => 'custom-token',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $this->assertEquals(
            'custom-token',
            $api->getServiceAuth('app.bsky.video.uploadVideo', 60, 'did:web:example.com')
        );
    }

    public function testGetServiceAuthMissingToken(): void
    {
        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'accessJwt',
                    'did' => 'did:plc:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => self::generateDidDocument(),
            ],
            [
                'status' => 200,
                'body' => [
                    'unexpected' => 'value',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $this->expectException(InvalidPayloadException::class);
        $api->getServiceAuth('com.atproto.repo.uploadBlob');
    }

    public function testUploadVideo(): void
    {
        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'accessJwt',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'jobStatus' => VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_COMPLETED'),
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $status = $api->uploadVideo('video-data', 'clip.mp4', 'video/mp4', 'service-token');

        $this->assertEquals('job-123', $status->getJobId());
        $this->assertEquals('JOB_STATE_COMPLETED', $status->getState());
        $this->assertNotNull($status->getBlob());
    }

    public function testUploadVideoWithHttpError(): void
    {
        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'accessJwt',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 400,
                'body' => [
                    'error' => 'BadRequest',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $this->expectException(HttpStatusCodeException::class);
        $api->uploadVideo('video-data', 'clip.mp4', 'video/mp4', 'service-token');
    }

    public function testGetVideoJobStatus(): void
    {
        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'jobStatus' => VideoJobStatusResponseTest::generateJobStatus('JOB_STATE_PROCESSING'),
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $status = $api->getVideoJobStatus('job-123');

        $this->assertEquals('job-123', $status->getJobId());
        $this->assertEquals('JOB_STATE_PROCESSING', $status->getState());
    }

    public function testGetVideoJobStatusInvalidPayload(): void
    {
        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => 'not-json',
                'jsonEncode' => false,
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent);

        $this->expectException(InvalidPayloadException::class);
        $api->getVideoJobStatus('job-123');
    }

    public function testReusesCachedSession(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'accessJwt' => 'cachedAccessToken',
            'did' => 'did:bluesky:1234567890',
            'refreshJwt' => 'cachedRefreshToken',
            'handle' => 'handle',
        ]);

        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'uri' => 'my-uri',
                    'cid' => 'cid:1234567890',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent, cache: $cache);

        $response = $api->createRecord(Post::create('Test for a post'));

        $this->assertEquals('my-uri', $response->getUri()->getUri());
    }

    public function testStoresCreatedSessionInCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with($this->cacheKey(), $this->isArray(), $this->isInt());

        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'token',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'uri' => 'my-uri',
                    'cid' => 'cid:1234567890',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent, cache: $cache);

        $api->createRecord(Post::create('Test for a post'));
    }

    public function testRefreshesExpiredSessionAndRetries(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'accessJwt' => 'expiredToken',
            'did' => 'did:bluesky:1234567890',
            'refreshJwt' => 'refreshToken',
            'handle' => 'handle',
        ]);
        $cache->expects($this->once())->method('set');

        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 401,
                'body' => [
                    'error' => 'ExpiredToken',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'newToken',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'newRefresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'uri' => 'my-uri',
                    'cid' => 'cid:1234567890',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent, cache: $cache);

        $response = $api->createRecord(Post::create('Test for a post'));

        $this->assertEquals('my-uri', $response->getUri()->getUri());
    }

    public function testRefreshFailureClearsCacheAndThrows(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'accessJwt' => 'expiredToken',
            'did' => 'did:bluesky:1234567890',
            'refreshJwt' => 'refreshToken',
            'handle' => 'handle',
        ]);
        $cache->expects($this->once())->method('delete')->with($this->cacheKey());

        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 401,
                'body' => [
                    'error' => 'ExpiredToken',
                ],
            ],
            [
                'status' => 401,
                'body' => [
                    'error' => 'InvalidToken',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent, cache: $cache);

        $this->expectException(AuthenticationErrorException::class);
        $api->createRecord(Post::create('Test for a post'));
    }

    public function testCreatesNewSessionAfterRefreshFailure(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'accessJwt' => 'expiredToken',
            'did' => 'did:bluesky:1234567890',
            'refreshJwt' => 'refreshToken',
            'handle' => 'handle',
        ]);
        $cache->expects($this->once())->method('delete');

        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 401,
                'body' => [
                    'error' => 'ExpiredToken',
                ],
            ],
            [
                'status' => 401,
                'body' => [
                    'error' => 'InvalidToken',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'newToken',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'newRefresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'uri' => 'my-uri',
                    'cid' => 'cid:1234567890',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent, cache: $cache);

        try {
            $api->createRecord(Post::create('First post'));
            $this->fail('Expected AuthenticationErrorException');
        } catch (AuthenticationErrorException) {
            // The dead session was discarded; the next call starts a fresh login.
        }

        $response = $api->createRecord(Post::create('Second post'));

        $this->assertEquals('my-uri', $response->getUri()->getUri());
    }

    public function testIgnoresCorruptCachedSession(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn([
            'unexpected' => 'value',
        ]);
        $cache->expects($this->once())->method('delete')->with($this->cacheKey());

        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'token',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'uri' => 'my-uri',
                    'cid' => 'cid:1234567890',
                ],
            ],
        ]);
        $api = new BlueskyApi('identifier', 'password', $httpComponent, cache: $cache);

        $response = $api->createRecord(Post::create('Test for a post'));

        $this->assertEquals('my-uri', $response->getUri()->getUri());
    }

    public function testSupportsEmailIdentifierInCacheKey(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());

        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'token',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'uri' => 'my-uri',
                    'cid' => 'cid:1234567890',
                ],
            ],
        ]);
        $api = new BlueskyApi('user@example.com', 'password', $httpComponent, cache: $cache);

        $response = $api->createRecord(Post::create('Test for a post'));

        $this->assertEquals('my-uri', $response->getUri()->getUri());
    }

    public function testSupportsDidIdentifierInCacheKey(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());

        $httpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'token',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'uri' => 'my-uri',
                    'cid' => 'cid:1234567890',
                ],
            ],
        ]);
        $api = new BlueskyApi('did:plc:abcdef123456', 'password', $httpComponent, cache: $cache);

        $response = $api->createRecord(Post::create('Test for a post'));

        $this->assertEquals('my-uri', $response->getUri()->getUri());
    }

    public function testReusesSessionStoredInPsr16Cache(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());

        $firstHttpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'accessJwt' => 'token',
                    'did' => 'did:bluesky:1234567890',
                    'refreshJwt' => 'refresh',
                    'handle' => 'handle',
                ],
            ],
            [
                'status' => 200,
                'body' => [
                    'uri' => 'my-uri',
                    'cid' => 'cid:1234567890',
                ],
            ],
        ]);
        $firstApi = new BlueskyApi('identifier', 'password', $firstHttpComponent, cache: $cache);
        $firstApi->createRecord(Post::create('Test for a post'));

        // The second client reuses the session from the cache and therefore only
        // issues the createRecord request.
        $secondHttpComponent = $this->generateHttpComponentsManagerFromResponses([
            [
                'status' => 200,
                'body' => [
                    'uri' => 'second-uri',
                    'cid' => 'cid:2',
                ],
            ],
        ]);
        $secondApi = new BlueskyApi('identifier', 'password', $secondHttpComponent, cache: $cache);

        $response = $secondApi->createRecord(Post::create('Second post'));

        $this->assertEquals('second-uri', $response->getUri()->getUri());
    }

    private function cacheKey(string $identifier = 'identifier'): string
    {
        return hash('sha256', 'bluesky_session_' . $identifier);
    }

    private static function generateDidDocument(): \stdClass
    {
        $service = new \stdClass();
        $service->id = '#atproto_pds';
        $service->type = 'AtprotoPersonalDataServer';
        $service->serviceEndpoint = 'https://morel.us-east.host.bsky.network';

        $document = new \stdClass();
        $document->id = 'did:plc:1234567890';
        $document->service = [$service];

        return $document;
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

    /**
     * @param list<array{status: int, body: array|string|\stdClass, jsonEncode?: bool}> $responses
     */
    private function generateHttpComponentsManagerFromResponses(array $responses): HttpComponentsManager
    {
        $psr17Factory = new Psr17Factory();

        $mocks = [];
        foreach ($responses as $spec) {
            $body = $spec['body'];
            if (($spec['jsonEncode'] ?? true) || ! is_string($body)) {
                $body = json_encode($body);
            }
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn($spec['status']);
            /** @psalm-suppress PossiblyFalseArgument */
            $stream = $psr17Factory->createStream($body);
            $response->method('getBody')->willReturn($stream);
            $mocks[] = $response;
        }

        $httpClient = $this->createMock(ClientInterface::class);
        call_user_func_array([$httpClient->method('sendRequest'), 'willReturn'], $mocks);

        return new HttpComponentsManager(
            $httpClient,
            $this->createMock(UriFactoryInterface::class),
            $psr17Factory,
            $psr17Factory
        );
    }
}
