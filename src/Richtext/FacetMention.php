<?php

declare(strict_types=1);

namespace potibm\Bluesky\Richtext;

class FacetMention extends AbstractFacet
{
    private string $did = '';

    public function __construct()
    {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function setDid(string $did): void
    {
        $this->did = $did;
    }

    protected function getFeature(): array
    {
        return [
            '$type' => parent::TYPE . '#mention',
            "did" => $this->did,
        ];
    }

    public static function create(string $did, int $start, int $end): self
    {
        $mention = new self();
        $mention->setStart($start);
        $mention->setEnd($end);
        $mention->setDid($did);
        return $mention;
    }
}
