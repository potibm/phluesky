<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Embed\External;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Embed\Record;
use potibm\Bluesky\Exception\FileNotFoundException;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;
use potibm\Bluesky\Richtext\FacetTag;

class BlueskyPostService
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

    public function addImage(Post $post, string $imageFile, string $altText): Post
    {
        $blob = $this->createBlobFromFilename($imageFile);

        $resultPost = clone $post;
        $embed = $resultPost->getEmbed();
        if (! $embed instanceof Images) {
            $embed = new Images();
            $resultPost->setEmbed($embed);
        }
        $embed->addImage($blob, $altText);

        return $resultPost;
    }

    public function addWebsiteCard(Post $post, string $uri, string $title, string $description, ?string $imageFile = null): Post
    {
        $resultPost = clone $post;

        if ($imageFile !== null) {
            $blob = $this->createBlobFromFilename($imageFile);
        } else {
            $blob = null;
        }

        $card = External::create($uri, $title, $description, $blob);
        $resultPost->setEmbed($card);

        return $resultPost;
    }

    private function createBlobFromFilename(string $imageFile): UploadBlobResponse
    {
        if (! file_exists($imageFile)) {
            throw new FileNotFoundException('File not found: ' . $imageFile);
        }

        return $this->blueskyClient->uploadBlob(
            file_get_contents($imageFile),
            mime_content_type($imageFile)
        );
    }
}
