<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Media;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Exception\FileNotFoundException;
use potibm\Bluesky\Media\FileMediaSource;

#[CoversClass(FileMediaSource::class)]
#[UsesClass(FileNotFoundException::class)]
final class FileMediaSourceTest extends TestCase
{
    public function testLoadsDataAndMimeTypeFromFile(): void
    {
        $source = new FileMediaSource(__FILE__);

        $this->assertSame(file_get_contents(__FILE__), $source->getData());
        $this->assertSame(mime_content_type(__FILE__), $source->getMimeType());
    }

    public function testThrowsExceptionForMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessageMatches('/^File not found/');

        new FileMediaSource(__DIR__ . '/missingfile.png');
    }

    public function testThrowsExceptionForUnreadableFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessageMatches('/^Unable to read file/');

        $root = vfsStream::setup('root');
        $file = vfsStream::newFile('image.png', 0000)->at($root);

        new FileMediaSource($file->url());
    }
}
