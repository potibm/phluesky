<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Embed\AspectRatio;
use potibm\Bluesky\Embed\External;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Embed\Record;
use potibm\Bluesky\Embed\Video;
use potibm\Bluesky\Exception\VideoUploadException;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Media\FileMediaSource;
use potibm\Bluesky\Media\MediaSource;
use potibm\Bluesky\Response\UploadBlobResponseInterface;
use potibm\Bluesky\Response\VideoJobStatusResponse;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;
use potibm\Bluesky\Richtext\FacetTag;

final class BlueskyPostService
{
    private const REGEXP_HANDLE = '([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)' .
        '+[a-zA-Z]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

    private const REGEXP_URL = 'https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~\#=]{1,256}\.' .
        '[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~\#?&//=]*[-a-zA-Z0-9@%_\+~\#//=])?';

    private const VIDEO_MIME_TYPE_MP4 = 'video/mp4';

    private const DEFAULT_VIDEO_FILENAME = 'video.mp4';

    private const FILE_PATH_DEPRECATION_MESSAGE = 'Passing a file path string is deprecated. Use FileMediaSource instead.';

    public function __construct(
        private BlueskyApiInterface $blueskyClient,
        private int $videoPollingIntervalMilliseconds = 1000,
        private int $videoPollingMaxAttempts = 300
    ) {
    }

    public function addFacetsFromMentionsAndLinks(Post $post): Post
    {
        $resultPost = clone $post;

        $resultPost = $this->addFacetsFromMentions($resultPost);
        $resultPost = $this->addFacetsFromLinks($resultPost);

        return $resultPost;
    }

    public function addFacetsFromMentionsAndLinksAndTags(Post $post): Post
    {
        $resultPost = $this->addFacetsFromMentionsAndLinks($post);
        $resultPost = $this->addFacetsFromTags($resultPost);

        return $resultPost;
    }

    public function addFacetsFromMentions(Post $post): Post
    {
        $resultPost = clone $post;

        $pattern = '#(?<=^|\W)(@' . self::REGEXP_HANDLE . ')#';
        preg_match_all($pattern, $post->getText(), $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {
            $handle = $match[0];
            $start = $match[1];

            $did = $this->blueskyClient->getDidForHandle(substr($handle, 1));
            $facet = FacetMention::create(
                $did,
                $start,
                $start + strlen($handle)
            );

            $resultPost->addFacet($facet);
        }

        return $resultPost;
    }

    public function addFacetsFromLinks(Post $post): Post
    {
        $resultPost = clone $post;

        $pattern = '#(?<=^|\W)(' . self::REGEXP_URL . ')#';
        preg_match_all($pattern, $post->getText(), $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            $url = $match[0];
            $start = $match[1];

            $facet = FacetLink::create(
                $url,
                $start,
                $start + strlen($url)
            );

            $resultPost->addFacet($facet);
        }

        return $resultPost;
    }

    public function addFacetsFromTags(Post $post): Post
    {
        $resultPost = clone $post;

        preg_match_all('/(#\w+)/u', $post->getText(), $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            $hashtag = $match[0];
            $start = $match[1];

            $facet = FacetTag::create(
                str_replace('#', '', $hashtag),
                $start,
                $start + strlen($hashtag)
            );

            $resultPost->addFacet($facet);
        }

        return $resultPost;
    }

    public function addQuote(Post $post, string $quotedRecordUri): Post
    {
        $resultPost = clone $post;

        $quotedRecord = $this->blueskyClient->getRecord(new BlueskyUri($quotedRecordUri));

        $resultPost->setEmbed(Record::createFromRecordResponse($quotedRecord));

        return $resultPost;
    }

    public function addReply(Post $post, string $replyParentUri): Post
    {
        $resultPost = clone $post;

        $replyParentRecord = $this->blueskyClient->getRecord(new BlueskyUri($replyParentUri));
        $replyRootRecord = $replyParentRecord;

        $replyRootRecordValue = $replyParentRecord->getReplyRoot();
        if ($replyRootRecordValue) {
            $replyRootRecord = $this->blueskyClient->getRecord($replyRootRecordValue->getUri());
        }

        $resultPost->setReply($replyRootRecord, $replyParentRecord);

        return $resultPost;
    }

    /**
     * @param string|MediaSource $imageFile a MediaSource, or a file path (deprecated)
     */
    public function addImage(Post $post, string|MediaSource $imageFile, string $altText, ?AspectRatio $aspectRatio = null): Post
    {
        if (is_string($imageFile)) {
            trigger_error(
                self::FILE_PATH_DEPRECATION_MESSAGE,
                E_USER_DEPRECATED
            );

            return $this->addImage($post, new FileMediaSource($imageFile), $altText, $aspectRatio);
        }

        $blob = $this->uploadMediaSource($imageFile);

        if ($aspectRatio === null) {
            $size = @getimagesizefromstring($imageFile->getData());
            if ($size !== false) {
                $aspectRatio = new AspectRatio($size[0], $size[1]);
            }
        }

        $resultPost = clone $post;
        $embed = $resultPost->getEmbed();
        if (! $embed instanceof Images) {
            $embed = new Images();
            $resultPost->setEmbed($embed);
        }
        $embed->addImage($blob, $altText, $aspectRatio);

        return $resultPost;
    }

    /**
     * @param string|MediaSource|null $imageFile a MediaSource, or a file path (deprecated)
     */
    public function addWebsiteCard(Post $post, string $uri, string $title, string $description, string|MediaSource|null $imageFile = null): Post
    {
        if (is_string($imageFile)) {
            trigger_error(
                self::FILE_PATH_DEPRECATION_MESSAGE,
                E_USER_DEPRECATED
            );

            return $this->addWebsiteCard($post, $uri, $title, $description, new FileMediaSource($imageFile));
        }

        $resultPost = clone $post;

        if ($imageFile !== null) {
            $blob = $this->uploadMediaSource($imageFile);
        } else {
            $blob = null;
        }

        $card = External::create($uri, $title, $description, $blob);
        $resultPost->setEmbed($card);

        return $resultPost;
    }

    /**
     * Attach a video to the post.
     *
     * The video is uploaded through the Bluesky video service
     * (app.bsky.video.uploadVideo), processed asynchronously and stored on the
     * PDS. This supports larger videos (currently up to 300 MB) but only accepts
     * video/mp4. The library polls the video service until processing completes.
     *
     * @param string|MediaSource $videoFile a MediaSource, or a file path (deprecated)
     * @param string|null        $filename  filename reported to the video service, defaults to "video.mp4"
     *
     * @throws VideoUploadException when the upload fails or processing times out
     */
    public function addVideo(
        Post $post,
        string|MediaSource $videoFile,
        string $alt = '',
        ?AspectRatio $aspectRatio = null,
        ?string $filename = null
    ): Post {
        if (is_string($videoFile)) {
            trigger_error(
                self::FILE_PATH_DEPRECATION_MESSAGE,
                E_USER_DEPRECATED
            );

            return $this->addVideo($post, new FileMediaSource($videoFile), $alt, $aspectRatio, $filename);
        }

        $blob = $this->uploadVideoViaService($videoFile, $filename);

        return $this->attachVideo($post, $blob, $alt, $aspectRatio);
    }

    /**
     * Attach a video blob that was uploaded previously to the post.
     *
     * Use this when you want to drive the upload and processing yourself, for
     * example to poll the video service in the background instead of blocking:
     *
     *   $token = $api->getServiceAuth('com.atproto.repo.uploadBlob');
     *   $job = $api->uploadVideo($data, 'clip.mp4', 'video/mp4', $token);
     *   // ... poll $api->getVideoJobStatus($job->getJobId()) until completed ...
     *   $post = $postService->attachVideo($post, $job->getBlob(), 'alt text');
     */
    public function attachVideo(
        Post $post,
        UploadBlobResponseInterface $blob,
        string $alt = '',
        ?AspectRatio $aspectRatio = null
    ): Post {
        $resultPost = clone $post;
        $resultPost->setEmbed(Video::create($blob, $alt, $aspectRatio));

        return $resultPost;
    }

    /**
     * @throws VideoUploadException
     */
    private function uploadVideoViaService(MediaSource $source, ?string $filename): UploadBlobResponseInterface
    {
        if ($source->getMimeType() !== self::VIDEO_MIME_TYPE_MP4) {
            throw new VideoUploadException(
                'The advanced video upload only supports video/mp4, got ' . $source->getMimeType()
            );
        }

        $serviceAuthToken = $this->blueskyClient->getServiceAuth('com.atproto.repo.uploadBlob');

        $jobStatus = $this->blueskyClient->uploadVideo(
            $source->getData(),
            $filename ?? self::DEFAULT_VIDEO_FILENAME,
            $source->getMimeType(),
            $serviceAuthToken
        );

        return $this->waitForVideoJob($jobStatus);
    }

    /**
     * @throws VideoUploadException
     */
    private function waitForVideoJob(VideoJobStatusResponse $jobStatus): UploadBlobResponseInterface
    {
        $attempts = 0;

        while (! $jobStatus->isCompleted() && ! $jobStatus->isFailed()) {
            $attempts++;
            if ($attempts > $this->videoPollingMaxAttempts) {
                throw new VideoUploadException(
                    'Video processing did not complete within the expected time (job ' . $jobStatus->getJobId() . ')'
                );
            }

            usleep($this->videoPollingIntervalMilliseconds * 1000);
            $jobStatus = $this->blueskyClient->getVideoJobStatus($jobStatus->getJobId());
        }

        if ($jobStatus->isFailed()) {
            $reason = $jobStatus->getMessage() ?? $jobStatus->getFailureCode() ?? 'unknown error';
            throw new VideoUploadException(
                'Video processing failed (job ' . $jobStatus->getJobId() . '): ' . $reason
            );
        }

        $blob = $jobStatus->getBlob();
        if ($blob === null) {
            throw new VideoUploadException(
                'Video processing completed without a blob (job ' . $jobStatus->getJobId() . ')'
            );
        }

        return $blob;
    }

    private function uploadMediaSource(MediaSource $source): UploadBlobResponseInterface
    {
        return $this->blueskyClient->uploadBlob(
            $source->getData(),
            $source->getMimeType()
        );
    }
}
