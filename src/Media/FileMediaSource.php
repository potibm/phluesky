<?php

declare(strict_types=1);

namespace potibm\Bluesky\Media;

use potibm\Bluesky\Exception\FileNotFoundException;

final class FileMediaSource implements MediaSource
{
    private string $data;

    private string $mimeType;

    public function __construct(string $filePath)
    {
        if (! file_exists($filePath)) {
            throw new FileNotFoundException('File not found: ' . $filePath);
        }

        $fileContents = @file_get_contents($filePath);
        if ($fileContents === false) {
            throw new FileNotFoundException('Unable to read file: ' . $filePath);
        }

        $fileMimeType = @mime_content_type($filePath);
        if ($fileMimeType === false) {
            throw new FileNotFoundException('Unable to determine mime type for file: ' . $filePath);
        }

        $this->data = $fileContents;
        $this->mimeType = $fileMimeType;
    }

    #[\Override]
    public function getData(): string
    {
        return $this->data;
    }

    #[\Override]
    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
