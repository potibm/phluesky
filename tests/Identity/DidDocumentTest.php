<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\Identity\DidDocument;

#[CoversClass(DidDocument::class)]
final class DidDocumentTest extends TestCase
{
    public function testGetPdsEndpointById(): void
    {
        $document = new DidDocument(self::generateDidDocument());

        $this->assertEquals('https://morel.us-east.host.bsky.network', $document->getPdsEndpoint());
    }

    public function testGetPdsEndpointByType(): void
    {
        $service = new \stdClass();
        $service->id = '#some_other_id';
        $service->type = 'AtprotoPersonalDataServer';
        $service->serviceEndpoint = 'https://pds.example.com';

        $document = new \stdClass();
        $document->id = 'did:plc:1234567890';
        $document->service = [$service];

        $didDocument = new DidDocument($document);

        $this->assertEquals('https://pds.example.com', $didDocument->getPdsEndpoint());
    }

    public function testReturnsNullWithoutPdsService(): void
    {
        $document = new \stdClass();
        $document->id = 'did:plc:1234567890';
        $document->service = [];

        $didDocument = new DidDocument($document);

        $this->assertNull($didDocument->getPdsEndpoint());
    }

    public function testMissingId(): void
    {
        $document = new \stdClass();
        $document->service = [];

        try {
            new DidDocument($document);
            $this->fail('Expected InvalidPayloadException');
        } catch (InvalidPayloadException $e) {
            $this->assertStringContainsString('id', $e->getMessage());
        }
    }

    public static function generateDidDocument(): \stdClass
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
}
