<?php

declare(strict_types=1);

namespace potibm\Bluesky\Richtext;

class FacetLink extends AbstractFacet
{
    private string $uri = '';

    public function __construct()
    {
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    protected function getFeature(): array
    {
        return [
            '$type' => parent::TYPE . '#link',
            "uri" => $this->uri
        ];
    }

    public static function create(string $uri, int $start, int $end): self
    {
        $link = new self();
        $link->setStart($start);
        $link->setEnd($end);
        $link->setUri($uri);
        return $link;
    }
}
