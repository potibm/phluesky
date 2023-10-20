<?php

declare(strict_types=1);

namespace potibm\Bluesky\Feed;

use JsonSerializable;
use potibm\Bluesky\Richtext\AbstractFacet;

class Post implements JsonSerializable
{
    private const TYPE = 'app.bsky.feed.post';

    private string $text = '';

    private \DateTimeImmutable $createdAt;

    private array $facets = [];

    private array $langs = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getFacets(): array
    {
        return $this->facets;
    }

    public function addFacet(AbstractFacet $facet): void
    {
        $this->facets[] = $facet;
    }

    public function removeAllFacets(): void
    {
        $this->facets = [];
    }

    public function getLangs(): array
    {
        return $this->langs;
    }

    public function setLangs(array $langs): void
    {
        $this->langs = $langs;
    }

    public function jsonSerialize(): mixed
    {
        $post = [
            '$type' => self::TYPE,
            'text' => $this->text,
            'createdAt' => $this->createdAt->format('Y-m-d\\TH:i:s.u\\Z'),
        ];
        if (count($this->langs)) {
            $post['langs'] = $this->langs;
        }
        if (count($this->facets)) {
            $post['facets'] = [];
            foreach ($this->facets as $facet) {
                $post['facets'][] = $facet->jsonSerialize();
            }
        }
        return $post;
    }

    public static function create(string $text, string $lang = 'en'): self
    {
        $post = new self();
        $post->setText($text);
        $post->setLangs([$lang]);

        return $post;
    }
}
