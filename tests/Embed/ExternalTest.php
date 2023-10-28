<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Embed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Embed\External;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Test\Response\UploadBlobResponseTest;

#[CoversClass(External::class)]
#[UsesClass(UploadBlobResponse::class)]
class ExternalTest extends TestCase
{
    public function testCreate(): void
    {
        $external = External::create('https://example.com', 'a title', 'a desc');

        $this->assertInstanceOf(External::class, $external);
        $this->assertEquals('https://example.com', $external->getUri());
        $this->assertEquals('a title', $external->getTitle());
        $this->assertEquals('a desc', $external->getDescription());
        $this->assertNull($external->getThumb());

        $this->assertEquals([
            '$type' => 'app.bsky.embed.external',
            'external' => [
                'uri' => 'https://example.com',
                'title' => 'a title',
                'description' => 'a desc',
            ],
        ], $external->jsonSerialize());
    }

    public function testWithThumb(): void
    {
        $blob = new UploadBlobResponse(UploadBlobResponseTest::generateBlobResponse());

        $external = new External();
        $external->setThumb($blob);

        $this->assertNotNull($external->getThumb());
        $json = $external->jsonSerialize();
        $this->assertArrayHasKey('external', $json);
        $this->assertArrayHasKey('thumb', $json['external']);
        $this->assertEquals($blob->jsonSerialize(), $json['external']['thumb']);
    }
}
