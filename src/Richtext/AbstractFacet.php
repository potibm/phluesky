<?php

declare(strict_types=1);

namespace potibm\Bluesky\Richtext;

use JsonSerializable;

abstract class AbstractFacet implements JsonSerializable
{
    protected const TYPE = 'app.bsky.richtext.facet';

    private int $start = 0;

    private int $end = 0;

    public function getStart(): int
    {
        return $this->start;
    }

    public function setStart(int $start): void
    {
        $this->start = $start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function setEnd(int $end): void
    {
        $this->end = $end;
    }


    abstract protected function getFeature(): array;

    public function jsonSerialize(): mixed
    {
        return [
            "index" => [
                "byteStart" => $this->start,
                "byteEnd" => $this->end
            ],
            "features" => [
                $this->getFeature()
            ]
        ];
    }
}
