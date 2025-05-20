<?php

declare(strict_types=1);

namespace potibm\Bluesky\Embed;

use potibm\Bluesky\Response\UploadBlobResponseInterface;

final class Images implements Embeddable, \Countable
{
    private array $images = [];

    public function addImage(UploadBlobResponseInterface $image, string $alt = '', ?AspectRatio $aspectRatio = null): void
    {
        $imageData = [
            'alt' => $alt,
            'image' => $image,
        ];

        if ($aspectRatio !== null) {
            $imageData['aspectRatio'] = $aspectRatio->toArray();
        }

        $this->images[] = $imageData;
    }

    public function clearImages(): void
    {
        $this->images = [];
    }

    #[\Override]
    public function count(): int
    {
        return count($this->images);
    }

    #[\Override]
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
