<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Embed\AspectRatio;
use potibm\Bluesky\Embed\External;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Embed\Record;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Media\FileMediaSource;
use potibm\Bluesky\Media\MediaSource;
use potibm\Bluesky\Response\UploadBlobResponseInterface;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;
use potibm\Bluesky\Richtext\FacetTag;

final class BlueskyPostService
{
    private const REGEXP_HANDLE = '([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)' .
        '+[a-zA-Z]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

    private const REGEXP_URL = 'https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~\#=]{1,256}\.' .
        '[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~\#?&//=]*[-a-zA-Z0-9@%_\+~\#//=])?';

    public function __construct(
        private BlueskyApiInterface $blueskyClient
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
                'Passing a file path string is deprecated. Use FileMediaSource instead.',
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
                'Passing a file path string is deprecated. Use FileMediaSource instead.',
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

    private function uploadMediaSource(MediaSource $source): UploadBlobResponseInterface
    {
        return $this->blueskyClient->uploadBlob(
            $source->getData(),
            $source->getMimeType()
        );
    }
}
