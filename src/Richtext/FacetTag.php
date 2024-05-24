<?php

namespace potibm\Bluesky\Richtext;

class FacetTag extends AbstractFacet
{
    private string $tag = '';

    public function __construct()
    {
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function setTag(string $tag): void
    {
        $this->tag = $tag;
    }

    protected function getFeature(): array
    {
        return [
            '$type' => parent::TYPE . '#tag',
            "tag" => $this->tag,
        ];
    }

    public static function create(string $tag, int $start, int $end): self
    {
        $link = new self();
        $link->setStart($start);
        $link->setEnd($end);
        $link->setTag($tag);
        return $link;
    }
}
