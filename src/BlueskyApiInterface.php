<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\CreateRecordResponse;
use potibm\Bluesky\Response\UploadBlobResponse;

interface BlueskyApiInterface
{
    public function getDidForHandle(string $handle): string;

    public function createRecord(Post $post): CreateRecordResponse;

    public function uploadBlob(string $image, string $mimeType): UploadBlobResponse;
}
