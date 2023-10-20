<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\CreateRecordResponse;

interface BlueskyApiInterface
{
    public function getDidForHandle(string $handle): string;

    public function createRecord(Post $post): CreateRecordResponse;
}
