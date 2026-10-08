<?php

declare(strict_types=1);

namespace potibm\Bluesky\Media;

final class BlobMediaSource implements MediaSource
{
    public function __construct(
        private string $data,
        private string $mimeType
    ) {
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
