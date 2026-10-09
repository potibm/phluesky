<?php

declare(strict_types=1);

namespace potibm\Bluesky\Identity;

use potibm\Bluesky\Exception\InvalidPayloadException;

final class DidDocument
{
    private const PDS_SERVICE_ID = '#atproto_pds';

    private const PDS_SERVICE_TYPE = 'AtprotoPersonalDataServer';

    /**
     * @var list<\stdClass>
     */
    private array $services = [];

    public function __construct(\stdClass $document)
    {
        if (! property_exists($document, 'id') || ! is_string($document->id)) {
            throw new InvalidPayloadException('DID document does not contain an "id" property');
        }

        if (property_exists($document, 'service') && is_array($document->service)) {
            foreach ($document->service as $service) {
                if ($service instanceof \stdClass) {
                    $this->services[] = $service;
                }
            }
        }
    }

    /**
     * Return the service endpoint of the Personal Data Server (PDS) hosting the
     * account, e.g. "https://morel.us-east.host.bsky.network".
     */
    public function getPdsEndpoint(): ?string
    {
        foreach ($this->services as $service) {
            if (! $this->isPdsService($service)) {
                continue;
            }

            $endpoint = $service->{'serviceEndpoint'} ?? null;
            if (is_string($endpoint) && $endpoint !== '') {
                return $endpoint;
            }
        }

        return null;
    }

    private function isPdsService(\stdClass $service): bool
    {
        $id = isset($service->id) && is_string($service->id) ? $service->id : '';
        $type = isset($service->type) && is_string($service->type) ? $service->type : '';

        return $id === self::PDS_SERVICE_ID || $type === self::PDS_SERVICE_TYPE;
    }
}
