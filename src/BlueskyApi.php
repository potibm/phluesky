<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Exception\HttpRequestException;
use potibm\Bluesky\Exception\HttpStatusCodeException;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\CreateRecordResponse;
use potibm\Bluesky\Response\CreateSessionResponse;
use Psr\Http\Client\ClientExceptionInterface;

class BlueskyApi implements BlueskyApiInterface
{
    private const BASE_URL = 'https://bsky.social/';

    private ?CreateSessionResponse $session = null;

    public function __construct(
        private string $identifier,
        private string $password,
        private HttpComponentsManager $options = new HttpComponentsManager(),
        private string $baseUrl = self::BASE_URL
    ) {
    }

    public function getDidForHandle(string $handle): string
    {
        $jsonBody = $this->performXrpcCall(
            'GET',
            'com.atproto.identity.resolveHandle',
            ['handle' => $handle],
            [],
            false
        );

        if (!property_exists($jsonBody, 'did')) {
            // Handle missing "did" property in JSON
            throw new InvalidPayloadException('JSON response does not contain "did" property');
        }

        return $jsonBody->did;
    }

    public function createRecord(Post $post): CreateRecordResponse
    {
        return new CreateRecordResponse($this->performXrpcCall(
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
            false
        ));
    }

    private function performXrpcCall(
        string $httpMethod,
        string $method,
        array $params = [],
        array $body = [],
        bool $autheticated = true
    ): \stdClass {
        $uri = $this->baseUrl . 'xrpc/' . $method;
        if ($params) {
            $uri .= '?' . http_build_query($params);
        }
        $uriObject = $this->options->uriFactory->createUri($uri);

        $request = $this->options->requestFactory->createRequest($httpMethod, $uriObject);
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');
        if ($autheticated) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->getSession()->getAuthToken());
        }
        if ($body) {
            $bodyObject = $this->options->streamFactory->createStream(
                json_encode($body)
            );
            $request = $request->withBody($bodyObject);
        }

        try {
            $response = $this->options->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // Handle network or HTTP client errors
            throw new HttpRequestException('Failed to send the request: ' . $e->getMessage());
        }

        if ($response->getStatusCode() != 200) {
            throw new HttpStatusCodeException('Received an HTTP error: ' . $response->getStatusCode());
        }

        $jsonBody = json_decode((string) $response->getBody(), false);

        if ($jsonBody === null) {
            // Handle JSON decoding errors
            throw new InvalidPayloadException('Failed to decode JSON response');
        }

        return $jsonBody;
    }
}
