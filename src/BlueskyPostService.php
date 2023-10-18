<?php

declare(strict_types=1);

namespace potibm\Bluesky;

use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;

class BlueskyPostService
{
    private const REGEXP_HANDLE = '([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)' .
        '+[a-zA-Z]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

    private const REGEXP_URL = 'https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~\#=]{1,256}\.' .
        '[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~\#?&//=]*[-a-zA-Z0-9@%_\+~\#//=])?';

    public function __construct(private BlueskyApi $blueskyClient)
    {
    }

    public function addFacetsFromMentionsAndLinks(Post $post): Post
    {
        $resultPost = clone $post;

        $resultPost = $this->addFacetsFromMentions($resultPost);
        $resultPost = $this->addFacetsFromLinks($resultPost);

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
}
