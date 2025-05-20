<?php

declare(strict_types=1);

namespace potibm\Bluesky\Feed;

use JsonSerializable;
use potibm\Bluesky\Embed\Embeddable;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Richtext\AbstractFacet;

final class Post implements JsonSerializable
{
    private const TYPE = 'app.bsky.feed.post';

    private string $text = '';

    private \DateTimeImmutable $createdAt;

    private array $facets = [];

    private array $langs = [];

    private ?array $reply = null;

    private ?Embeddable $embed = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
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
        $this->createdAt = $createdAt->setTimezone(new \DateTimeZone('UTC'));
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

    public function getEmbed(): ?Embeddable
    {
        return $this->embed;
    }

    public function setEmbed(Embeddable $embed): void
    {
        $this->embed = $embed;
    }

    public function setReply(RecordResponse $root, RecordResponse $parent): void
    {
        $this->reply = [
            'root' => $root,
            'parent' => $parent,
        ];
    }

    public function removeReply(): void
    {
        $this->reply = null;
    }

    #[\Override]
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
        if ($this->embed) {
            $post['embed'] = $this->embed->jsonSerialize();
        }
        if ($this->reply !== null) {
            $post['reply'] = [
                'root' => $this->convertRecordReponseToArray($this->reply['root']),
                'parent' => $this->convertRecordReponseToArray($this->reply['parent']),
            ];
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

    private function convertRecordReponseToArray(RecordResponse $response): array
    {
        return [
            'uri' => (string) $response->getUri(),
            'cid' => $response->getCid(),
        ];
    }
}
