<?php

declare(strict_types=1);

namespace potibm\Bluesky\Embed;

use potibm\Bluesky\Response\UploadBlobResponse;

class External implements Embeddable
{
    private string $uri = '';

    private string $title = '';

    private string $description = '';

    private ?UploadBlobResponse $thumb = null;

    public function jsonSerialize(): mixed
    {
        $json = [
            '$type' => "app.bsky.embed.external",
            "external" => [
                "uri" => $this->uri,
                "title" => $this->title,
                "description" => $this->description,
            ],
        ];

        if ($this->thumb) {
            $json['external']['thumb'] = $this->thumb->jsonSerialize();
        }

        return $json;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getThumb(): ?UploadBlobResponse
    {
        return $this->thumb;
    }

    public function setThumb(?UploadBlobResponse $thumb): void
    {
        $this->thumb = $thumb;
    }

    public static function create(string $uri, string $title, string $description, ?UploadBlobResponse $thumb = null): self
    {
        $external = new self();
        $external->setUri($uri);
        $external->setTitle($title);
        $external->setDescription($description);
        $external->setThumb($thumb);

        return $external;
    }
}
