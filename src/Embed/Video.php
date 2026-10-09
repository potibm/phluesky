<?php

declare(strict_types=1);

namespace potibm\Bluesky\Embed;

use potibm\Bluesky\Response\UploadBlobResponseInterface;

final class Video implements Embeddable
{
    private string $alt = '';

    private ?AspectRatio $aspectRatio = null;

    public function __construct(
        private UploadBlobResponseInterface $video
    ) {
    }

    #[\Override]
    public function jsonSerialize(): mixed
    {
        $json = [
            '$type' => 'app.bsky.embed.video',
            'video' => $this->video->jsonSerialize(),
        ];

        if ($this->alt !== '') {
            $json['alt'] = $this->alt;
        }

        if ($this->aspectRatio !== null) {
            $json['aspectRatio'] = $this->aspectRatio->toArray();
        }

        return $json;
    }

    public function getVideo(): UploadBlobResponseInterface
    {
        return $this->video;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function setAlt(string $alt): void
    {
        $this->alt = $alt;
    }

    public function getAspectRatio(): ?AspectRatio
    {
        return $this->aspectRatio;
    }

    public function setAspectRatio(?AspectRatio $aspectRatio): void
    {
        $this->aspectRatio = $aspectRatio;
    }

    public static function create(
        UploadBlobResponseInterface $video,
        string $alt = '',
        ?AspectRatio $aspectRatio = null
    ): self {
        $videoEmbed = new self($video);
        $videoEmbed->setAlt($alt);
        $videoEmbed->setAspectRatio($aspectRatio);

        return $videoEmbed;
    }
}
