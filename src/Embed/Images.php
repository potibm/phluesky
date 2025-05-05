<?php

declare(strict_types=1);

namespace potibm\Bluesky\Embed;

use potibm\Bluesky\Response\UploadBlobResponse;

class Images implements Embeddable, \Countable
{
    private array $images = [];

    public function addImage(UploadBlobResponse $image, string $alt = '', ?aspectRatio $aspectRatio = null): void
    {
        $imageData = [
            'alt' => $alt,
            'image' => $image,
        ];

        if ($aspectRatio && isset($aspectRatio['width'], $aspectRatio['height'])) {
            $imageData['aspectRatio'] = $aspectRatio->toArray();
        }

        $this->images[] = $imageData;
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

    public static function create(): self
    {
        return new self();
    }
}
