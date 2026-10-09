<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Exception\AuthenticationErrorException;
use potibm\Bluesky\Exception\HttpRequestException;
use potibm\Bluesky\Exception\HttpStatusCodeException;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Identity\DidResolver;
use potibm\Bluesky\Response\CreateSessionResponse;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Response\VideoJobStatusResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

final class BlueskyApi implements BlueskyApiInterface
{
    private const BASE_URL = 'https://bsky.social/';

    private const VIDEO_SERVICE_URL = 'https://video.bsky.app/';

    private const HTTP_OK = 200;

    private const HTTP_UNAUTHORIZED = 401;

    private const MIME_TYPE_JSON = 'application/json';

    private const AUTH_HEADER_PREFIX = 'Bearer ';

    /**
     * Refresh tokens are valid for roughly two months, see
     * https://atproto.com/specs/xrpc#authentication.
     */
    private const SESSION_TTL = 60 * 24 * 60 * 60;

    private ?CreateSessionResponse $session = null;

    private ?string $pdsDid = null;

    private bool $isRetrying = false;

    public function __construct(
        private string $identifier,
        private string $password,
        private HttpComponentsManager $options = new HttpComponentsManager(),
        private string $baseUrl = self::BASE_URL,
        private ?CacheInterface $cache = null,
        private string $videoServiceUrl = self::VIDEO_SERVICE_URL
    ) {
        if ($this->cache !== null) {
            $cachedSession = $this->cache->get($this->getCacheKey());
            if (is_array($cachedSession)) {
                try {
                    $this->session = CreateSessionResponse::fromArray($cachedSession);
                } catch (InvalidPayloadException) {
                    $this->cache->delete($this->getCacheKey());
                }
            }
        }
    }

    #[\Override]
    public function getDidForHandle(string $handle): string
    {
        $jsonBody = $this->performXrpcCall(
            'GET',
            'com.atproto.identity.resolveHandle',
            [
                'handle' => $handle,
            ],
            [],
            [],
            false
        );

        if (! property_exists($jsonBody, 'did')) {
            // Handle missing "did" property in JSON
            throw new InvalidPayloadException('JSON response does not contain "did" property');
        }

        return $jsonBody->did;
    }

    #[\Override]
    public function createRecord(Post $post): RecordResponse
    {
        return new RecordResponse($this->performXrpcCall(
            'POST',
            'com.atproto.repo.createRecord',
            [],
            [
                'repo' => $this->getSession()->getDid(),
                'collection' => "app.bsky.feed.post",
                "record" => $post->jsonSerialize(),
            ]
        ));
    }

    #[\Override]
    public function getRecord(BlueskyUri $uri): RecordResponse
    {
        return new RecordResponse($this->performXrpcCall(
            'GET',
            'com.atproto.repo.getRecord',
            [
                'repo' => $uri->getDID(),
                'collection' => $uri->getNSID(),
                'rkey' => $uri->getRecord(),
            ],
            [],
            [],
            false
        ));
    }

    #[\Override]
    public function uploadBlob(string $image, string $mimeType): UploadBlobResponse
    {
        $jsonBody = $this->performXrpcCall(
            'POST',
            'com.atproto.repo.uploadBlob',
            [],
            $image,
            [
                'Content-Type' => $mimeType,
            ],
            true,
            false
        );

        if (! property_exists($jsonBody, 'blob')) {
            throw new InvalidPayloadException('JSON response does not contain "blob" property');
        }

        return new UploadBlobResponse($jsonBody->blob);
    }

    #[\Override]
    public function getServiceAuth(string $lexiconMethod, int $expirySeconds = 1800, ?string $audience = null): string
    {
        $jsonBody = $this->performXrpcCall(
            'GET',
            'com.atproto.server.getServiceAuth',
            [
                'aud' => $audience ?? $this->getPdsAudience(),
                'lxm' => $lexiconMethod,
                'exp' => time() + $expirySeconds,
            ],
            [],
            [],
            true,
            false
        );

        if (! property_exists($jsonBody, 'token')) {
            throw new InvalidPayloadException('JSON response does not contain "token" property');
        }

        return (string) $jsonBody->token;
    }

    #[\Override]
    public function uploadVideo(string $video, string $filename, string $mimeType, string $serviceAuthToken): VideoJobStatusResponse
    {
        $jsonBody = $this->performVideoServiceCall(
            'POST',
            'app.bsky.video.uploadVideo',
            [
                'did' => $this->getSession()->getDid(),
                'name' => $filename,
            ],
            $video,
            $mimeType,
            $serviceAuthToken
        );

        return VideoJobStatusResponse::fromResponse($jsonBody);
    }

    #[\Override]
    public function getVideoJobStatus(string $jobId): VideoJobStatusResponse
    {
        $jsonBody = $this->performVideoServiceCall(
            'GET',
            'app.bsky.video.getJobStatus',
            [
                'jobId' => $jobId,
            ]
        );

        return VideoJobStatusResponse::fromResponse($jsonBody);
    }

    private function getPdsAudience(): string
    {
        if ($this->pdsDid === null) {
            $this->pdsDid = (new DidResolver($this->options))->resolvePdsDid($this->getSession()->getDid());
        }

        return $this->pdsDid;
    }

    /**
     * Perform an XRPC call against the video service. Unlike performXrpcCall()
     * this never uses the user session and does not attempt to refresh it.
     */
    private function performVideoServiceCall(
        string $httpMethod,
        string $method,
        array $params,
        string $body = '',
        string $mimeType = self::MIME_TYPE_JSON,
        ?string $bearerToken = null
    ): \stdClass {
        $uri = $this->videoServiceUrl . 'xrpc/' . $method;
        if ($params) {
            $uri .= '?' . http_build_query($params);
        }
        $uriObject = $this->options->uriFactory->createUri($uri);

        $request = $this->options->requestFactory->createRequest($httpMethod, $uriObject);
        $request = $request->withHeader('Accept', self::MIME_TYPE_JSON);
        if ($bearerToken !== null) {
            $request = $request->withHeader('Authorization', self::AUTH_HEADER_PREFIX . $bearerToken);
        }

        if ($body !== '') {
            $request = $request->withHeader('Content-Type', $mimeType);
            $request = $request->withHeader('Content-Length', (string) strlen($body));
            $request = $request->withBody($this->options->streamFactory->createStream($body));
        }

        return $this->decodeResponse($this->sendRequest($request));
    }

    private function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->options->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // Handle network or HTTP client errors
            throw new HttpRequestException('Failed to send the request: ' . $e->getMessage());
        }
    }

    private function decodeResponse(ResponseInterface $response): \stdClass
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== self::HTTP_OK) {
            throw new HttpStatusCodeException('Received an HTTP error (' . $statusCode . '): ' . (string) $response->getBody(), $statusCode);
        }

        $jsonBody = json_decode((string) $response->getBody(), false);

        if ($jsonBody === null) {
            // Handle JSON decoding errors
            throw new InvalidPayloadException('Failed to decode JSON response');
        }

        return $jsonBody;
    }

    private function getSession(): CreateSessionResponse
    {
        if ($this->session === null) {
            $this->session = $this->createSession();
        }

        return $this->session;
    }

    private function createSession(): CreateSessionResponse
    {
        $session = new CreateSessionResponse($this->performXrpcCall(
            'POST',
            'com.atproto.server.createSession',
            [],
            [
                'identifier' => $this->identifier,
                'password' => $this->password,
            ],
            [],
            false
        ));

        $this->saveSession($session);

        return $session;
    }

    private function refreshSession(): void
    {
        $session = $this->getSession();

        if ($session->getRefreshToken() === '') {
            throw new AuthenticationErrorException('Unable to refresh session: no refresh token available');
        }

        $jsonBody = $this->performXrpcCall(
            'POST',
            'com.atproto.server.refreshSession',
            [],
            [],
            [
                'Authorization' => self::AUTH_HEADER_PREFIX . $session->getRefreshToken(),
            ],
            false
        );

        $this->session = new CreateSessionResponse($jsonBody);
        $this->saveSession($this->session);
    }

    private function saveSession(CreateSessionResponse $session): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->set($this->getCacheKey(), $session->toArray(), self::SESSION_TTL);
    }

    private function clearCachedSession(): void
    {
        if ($this->cache === null) {
            return;
        }

        $this->cache->delete($this->getCacheKey());
    }

    private function getCacheKey(): string
    {
        // PSR-16 keys are limited to [A-Za-z0-9_.] and 64 characters, but Bluesky
        // identifiers may be an email or a DID (e.g. "did:plc:...") containing
        // reserved characters. Hash the key to stay within the PSR-16 constraints.
        return hash('sha256', 'bluesky_session_' . $this->identifier);
    }

    /**
     * @param (mixed|string)[]|string $body
     *
     * @psalm-param array{repo?: string, collection?: 'app.bsky.feed.post', record?: mixed, identifier?: string, password?: string}|string $body
     */
    private function performXrpcCall(
        string $httpMethod,
        string $method,
        array $params = [],
        array|string $body = [],
        array $headers = [],
        bool $authenticated = true,
        bool $encodeBody = true
    ): \stdClass {
        $request = $this->buildRequest($httpMethod, $method, $params, $body, $headers, $authenticated, $encodeBody);

        $response = $this->sendRequest($request);

        $statusCode = $response->getStatusCode();

        if ($statusCode === self::HTTP_UNAUTHORIZED) {
            if ($authenticated && ! $this->isRetrying && $this->session !== null && $this->session->getRefreshToken() !== '') {
                $this->isRetrying = true;

                try {
                    $this->refreshSession();

                    return $this->performXrpcCall($httpMethod, $method, $params, $body, $headers, $authenticated, $encodeBody);
                } catch (\Throwable $throwable) {
                    $this->session = null;
                    $this->clearCachedSession();
                    throw $throwable;
                } finally {
                    $this->isRetrying = false;
                }
            }

            throw new AuthenticationErrorException('Authentication failed: ' . (string) $response->getBody(), 401);
        }

        return $this->decodeResponse($response);
    }

    /**
     * @param (mixed|string)[]|string $body
     *
     * @psalm-param array{repo?: string, collection?: 'app.bsky.feed.post', record?: mixed, identifier?: string, password?: string}|string $body
     */
    private function buildRequest(
        string $httpMethod,
        string $method,
        array $params,
        array|string $body,
        array $headers,
        bool $authenticated,
        bool $encodeBody
    ): RequestInterface {
        $uri = $this->baseUrl . 'xrpc/' . $method;
        if ($params) {
            $uri .= '?' . http_build_query($params);
        }
        $uriObject = $this->options->uriFactory->createUri($uri);

        $headers = array_merge([
            'Content-Type' => self::MIME_TYPE_JSON,
            'Accept' => self::MIME_TYPE_JSON,
        ], $headers);
        if ($authenticated) {
            $headers['Authorization'] = self::AUTH_HEADER_PREFIX . $this->getSession()->getAuthToken();
        }

        $request = $this->options->requestFactory->createRequest($httpMethod, $uriObject);
        foreach ($headers as $header => $value) {
            $request = $request->withHeader($header, $value);
        }

        if ($body) {
            if ($encodeBody || ! is_string($body)) {
                $body = json_encode($body);
                if ($body === false) {
                    throw new InvalidPayloadException('Failed to encode body to JSON: ' . json_last_error_msg());
                }
            }
            $bodyObject = $this->options->streamFactory->createStream($body);
            $request = $request->withBody($bodyObject);
        }

        return $request;
    }
}
