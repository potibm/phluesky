<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Embed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Embed\AspectRatio;
use potibm\Bluesky\Embed\Video;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Test\Response\UploadBlobResponseTest;

#[CoversClass(Video::class)]
#[UsesClass(UploadBlobResponse::class)]
#[UsesClass(AspectRatio::class)]
final class VideoTest extends TestCase
{
    public function testCreateWithDefaults(): void
    {
        $blob = $this->createBlob();
        $video = Video::create($blob);

        $this->assertSame($blob, $video->getVideo());
        $this->assertEquals('', $video->getAlt());
        $this->assertNull($video->getAspectRatio());

        $json = $video->jsonSerialize();
        $this->assertIsArray($json);
        $this->assertEquals('app.bsky.embed.video', $json['$type']);
        $this->assertEquals($blob->jsonSerialize(), $json['video']);
        $this->assertArrayNotHasKey('alt', $json);
        $this->assertArrayNotHasKey('aspectRatio', $json);
    }

    public function testCreateWithAltAndAspectRatio(): void
    {
        $blob = $this->createBlob();
        $video = Video::create($blob, 'my alt text', new AspectRatio(16, 9));

        $this->assertEquals('my alt text', $video->getAlt());
        $this->assertEquals(new AspectRatio(16, 9), $video->getAspectRatio());

        $json = $video->jsonSerialize();
        $this->assertIsArray($json);
        $this->assertEquals('my alt text', $json['alt']);
        $this->assertEquals([
            'width' => 16,
            'height' => 9,
        ], $json['aspectRatio']);
    }

    public function testSetters(): void
    {
        $video = new Video($this->createBlob());

        $video->setAlt('updated alt');
        $video->setAspectRatio(new AspectRatio(1, 1));

        $this->assertEquals('updated alt', $video->getAlt());
        $this->assertEquals(new AspectRatio(1, 1), $video->getAspectRatio());
    }

    private function createBlob(): UploadBlobResponse
    {
        return new UploadBlobResponse(UploadBlobResponseTest::generateBlobResponse());
    }
}
