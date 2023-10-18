<?php

declare(strict_types=1);

namespace potibm\Bluesky;

interface BlueskyApiInterface
{
    public function getDidForHandle(string $handle): string;
}
