<?php

declare(strict_types=1);

namespace potibm\Bluesky\Embed;

use potibm\Bluesky\Response\UploadBlobResponse;

class Images implements \JsonSerializable, \Countable
{
    private array $images = [];

    public function addImage(UploadBlobResponse $image, string $alt = ''): void
    {
        $this->images[] = [
            'alt' => $alt,
            'image' => $image,
        ];
    }

    public function clearImages(): void
    {
        $this->images = [];
    }

    public function count(): int
    {
        return count($this->images);
    }

    public function jsonSerialize(): mixed
    {
        return [
            '$type' => 'app.bsky.embed.images',
            "images" => $this->images,
        ];
    }
}
