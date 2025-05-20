<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Response\UploadBlobResponse;

#[CoversClass(UploadBlobResponse::class)]
final class UploadBlobResponseTest extends TestCase
{
    public function testCreateValidObject(): void
    {
        $blob = new UploadBlobResponse(self::generateBlobResponse());
        $this->assertEquals('image/jpeg', $blob->getMimeType());
        $this->assertEquals(123, $blob->getSize());
        $this->assertEquals('https://example.com', $blob->getRefLink());
    }

    public function testMissingRefValue(): void
    {
        $response = new \stdClass();
        $response->mimeType = 'image/jpeg';
        $response->size = 123;

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        new UploadBlobResponse($response);
    }

    public function testJsonSerialize(): void
    {
        $blob = new UploadBlobResponse(self::generateBlobResponse());
        $this->assertEquals([
            '$type' => 'blob',
            "mimeType" => 'image/jpeg',
            "size" => 123,
            "ref" => [
                '$link' => 'https://example.com',
            ],
        ], $blob->jsonSerialize());
    }

    public static function generateBlobResponse(): \stdClass
    {
        $response = new \stdClass();
        $response->mimeType = 'image/jpeg';
        $response->size = 123;
        $response->ref = new \stdClass();
        $response->ref->{'$link'} = 'https://example.com';

        return $response;
    }
}
