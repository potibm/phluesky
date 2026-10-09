<?php

declare(strict_types=1);

namespace potibm\Bluesky\Identity;

use potibm\Bluesky\Exception\HttpRequestException;
use potibm\Bluesky\Exception\HttpStatusCodeException;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\HttpComponentsManager;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class DidResolver
{
    private const HTTP_OK = 200;

    private const MIME_TYPE_JSON = 'application/json';

    public function __construct(
        private HttpComponentsManager $options
    ) {
    }

    /**
     * Resolve the DID of the PDS that hosts the given account. The public
     * entryway (bsky.social) would report its own DID, so the account's DID
     * document is resolved to find the actual PDS service endpoint.
     */
    public function resolvePdsDid(string $accountDid): string
    {
        $endpoint = $this->fetchDidDocument($accountDid)->getPdsEndpoint();
        if ($endpoint === null) {
            throw new InvalidPayloadException('DID document does not contain an atproto PDS service: ' . $accountDid);
        }

        return $this->pdsDidFromEndpoint($endpoint);
    }

    private function fetchDidDocument(string $did): DidDocument
    {
        $uri = $this->options->uriFactory->createUri($this->buildDidDocumentUrl($did));
        $request = $this->options->requestFactory->createRequest('GET', $uri)
            ->withHeader('Accept', self::MIME_TYPE_JSON);

        return new DidDocument($this->decodeResponse($this->sendRequest($request)));
    }

    private function buildDidDocumentUrl(string $did): string
    {
        if (str_starts_with($did, 'did:plc:')) {
            return 'https://plc.directory/' . $did;
        }

        if (str_starts_with($did, 'did:web:')) {
            // did:web:example.com -> https://example.com/.well-known/did.json
            // did:web:example.com:path -> https://example.com/path/did.json
            // did:web:example.com%3A3000 -> https://example.com:3000/.well-known/did.json
            $segments = explode(':', substr($did, strlen('did:web:')));
            $host = str_replace(['%3A', '%3a'], ':', array_shift($segments));
            $path = $segments === [] ? '/.well-known/did.json' : '/' . implode('/', $segments) . '/did.json';

            return 'https://' . $host . $path;
        }

        throw new InvalidPayloadException('Unsupported DID method: ' . $did);
    }

    private function pdsDidFromEndpoint(string $endpoint): string
    {
        $host = parse_url($endpoint, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw new InvalidPayloadException('Unable to determine PDS host from endpoint: ' . $endpoint);
        }

        $port = parse_url($endpoint, PHP_URL_PORT);
        if (is_int($port)) {
            $host .= '%3A' . $port;
        }

        return 'did:web:' . $host;
    }

    private function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->options->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
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
            throw new InvalidPayloadException('Failed to decode JSON response');
        }

        return $jsonBody;
    }
}
