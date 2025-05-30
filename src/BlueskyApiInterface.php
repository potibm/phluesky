<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Response\UploadBlobResponseInterface;

interface BlueskyApiInterface
{
    public function getDidForHandle(string $handle): string;

    public function createRecord(Post $post): RecordResponse;

    public function uploadBlob(string $image, string $mimeType): UploadBlobResponseInterface;

    public function getRecord(BlueskyUri $uri): RecordResponse;
}
