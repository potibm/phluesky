<?php

declare(strict_types=1);

namespace potibm\Bluesky\Media;

interface MediaSource
{
    public function getData(): string;

    public function getMimeType(): string;
}
