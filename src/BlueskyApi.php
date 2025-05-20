<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Exception\AuthenticationErrorException;
use potibm\Bluesky\Exception\HttpRequestException;
use potibm\Bluesky\Exception\HttpStatusCodeException;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\CreateSessionResponse;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Response\UploadBlobResponse;
use Psr\Http\Client\ClientExceptionInterface;

final class BlueskyApi implements BlueskyApiInterface
{
    private const BASE_URL = 'https://bsky.social/';

    private const HTTP_OK = 200;

    private const HTTP_UNAUTHORIZED = 401;

    private ?CreateSessionResponse $session = null;

    public function __construct(
        private string $identifier,
        private string $password,
        private HttpComponentsManager $options = new HttpComponentsManager(),
        private string $baseUrl = self::BASE_URL
    ) {
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

    private function getSession(): CreateSessionResponse
    {
        if ($this->session === null) {
            $this->session = $this->createSession();
        }

        return $this->session;
    }

    private function createSession(): CreateSessionResponse
    {
        return new CreateSessionResponse($this->performXrpcCall(
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
        $uri = $this->baseUrl . 'xrpc/' . $method;
        if ($params) {
            $uri .= '?' . http_build_query($params);
        }
        $uriObject = $this->options->uriFactory->createUri($uri);

        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);
        if ($authenticated) {
            $headers['Authorization'] = 'Bearer ' . $this->getSession()->getAuthToken();
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

        try {
            $response = $this->options->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // Handle network or HTTP client errors
            throw new HttpRequestException('Failed to send the request: ' . $e->getMessage());
        }

        if ($response->getStatusCode() === self::HTTP_UNAUTHORIZED) {
            throw new AuthenticationErrorException('Authentication failed: ' . (string) $response->getBody(), 401);
        } elseif ($response->getStatusCode() != self::HTTP_OK) {
            throw new HttpStatusCodeException('Received an HTTP error (' . $response->getStatusCode() . '): ' . (string) $response->getBody(), $response->getStatusCode());
        }

        $jsonBody = json_decode((string) $response->getBody(), false);

        if ($jsonBody === null) {
            // Handle JSON decoding errors
            throw new InvalidPayloadException('Failed to decode JSON response');
        }

        return $jsonBody;
    }
}
