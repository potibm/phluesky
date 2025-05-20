<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Embed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Test\Response\UploadBlobResponseTest;

#[CoversClass(Images::class)]
#[UsesClass(UploadBlobResponse::class)]
final class ImagesTest extends TestCase
{
    public function testAndAndCount(): void
    {
        $images = Images::create();
        $this->assertEquals(0, $images->count());

        $images->addImage($this->createBlob());
        $this->assertEquals(1, $images->count());

        $images->addImage($this->createBlob());
        $this->assertEquals(2, $images->count());

        $images->clearImages();
        $this->assertEquals(0, $images->count());
    }

    public function testJsonSerialize(): void
    {
        $images = new Images();
        $blob = $this->createBlob();
        $images->addImage($blob, 'my alt text');

        $jsonOutput = $images->jsonSerialize();
        $this->assertIsArray($jsonOutput);
        $this->assertArrayHasKey('$type', $jsonOutput);
        $this->assertEquals('app.bsky.embed.images', $jsonOutput['$type']);

        $this->assertArrayHasKey('images', $jsonOutput);
        $this->assertEquals(1, count($jsonOutput['images']));

        $image = $jsonOutput['images'][0];
        $this->assertArrayHasKey('alt', $image);
        $this->assertEquals('my alt text', $image['alt']);

        $this->assertArrayHasKey('image', $image);
        $this->assertEquals($blob, $image['image']);
    }

    private function createBlob(): UploadBlobResponse
    {
        return new UploadBlobResponse(UploadBlobResponseTest::generateBlobResponse());
    }
}
