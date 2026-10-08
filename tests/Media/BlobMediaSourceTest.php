<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Media\BlobMediaSource;

#[CoversClass(BlobMediaSource::class)]
final class BlobMediaSourceTest extends TestCase
{
    public function testReturnsProvidedDataAndMimeType(): void
    {
        $source = new BlobMediaSource('binary-image-data', 'image/png');

        $this->assertSame('binary-image-data', $source->getData());
        $this->assertSame('image/png', $source->getMimeType());
    }
}
