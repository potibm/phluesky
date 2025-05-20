<?php

declare(strict_types=1);

namespace potibm\Bluesky\Embed;

use potibm\Bluesky\Response\JobStatusResponse;
use potibm\Bluesky\Response\RecordResponse;

class Video implements Embeddable {

    private int $width = 0;

    private int $height = 0;

    private string $blob = '';

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): void
    {
        $this->height = $height;
    }

    public function getBlob(): string
    {
        return $this->blob;
    }

    public function setBlob(string $blob): void
    {
        $this->blob = $blob;
    }

    public function jsonSerialize(): mixed
    {
        return [
            '$type' => 'app.bsky.embed.video',
            'video' => [
                'aspectRatio' => [
                    'width' =>$this->width,
                     'height' => $this->height,
                    ],
                'video' => $this->blob,
            ],
        ];
    }

    public static function create(string $blob, int $width, int $height): self
    {
        $video = new self();
        $video->setBlob($blob);
        $video->setWidth($width);
        $video->setHeight($height);

        return $video;
    }

    public static function createFromRecordResponse(JobStatusResponse $response, int $width, int $height): ?self
    {
        if (!$response->getBlob()) {
            return null;
        }
        return self::create($response->getBlob(),$width, $height);
    }
}