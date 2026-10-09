<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Response\UploadBlobResponseInterface;
use potibm\Bluesky\Response\VideoJobStatusResponse;

interface BlueskyApiInterface
{
    public function getDidForHandle(string $handle): string;

    public function createRecord(Post $post): RecordResponse;

    public function uploadBlob(string $image, string $mimeType): UploadBlobResponseInterface;

    public function getRecord(BlueskyUri $uri): RecordResponse;

    /**
     * Request a signed service token that can be used against another service.
     *
     * @param string      $lexiconMethod   the XRPC method the token is bound to
     * @param int         $expirySeconds   how long the token stays valid
     * @param string|null $audience        the DID of the target service; defaults to the PDS
     */
    public function getServiceAuth(string $lexiconMethod, int $expirySeconds = 1800, ?string $audience = null): string;

    /**
     * Upload a video to the video service for processing and storage on the PDS.
     *
     * @param string $video            raw video data
     * @param string $filename         the filename to report to the video service
     * @param string $mimeType         the mime type of the video (currently video/mp4)
     * @param string $serviceAuthToken token obtained from getServiceAuth()
     */
    public function uploadVideo(string $video, string $filename, string $mimeType, string $serviceAuthToken): VideoJobStatusResponse;

    /**
     * Fetch the processing status of a previously uploaded video.
     */
    public function getVideoJobStatus(string $jobId): VideoJobStatusResponse;
}
