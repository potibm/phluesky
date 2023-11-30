<?php

declare(strict_types=1);

namespace potibm\Bluesky\Embed;

use potibm\Bluesky\Response\RecordResponse;

class Record implements Embeddable
{
    private string $uri = '';

    private string $cid = '';

    public function jsonSerialize(): mixed
    {
        $json = [
            '$type' => "app.bsky.embed.record",
            "record" => [
                "uri" => $this->uri,
                "cid" => $this->cid,
            ],
        ];

        return $json;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function setUri(string $uri): void
    {
        $this->uri = $uri;
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function setCid(string $cid): void
    {
        $this->cid = $cid;
    }

    public static function create(string $uri, string $cid): self
    {
        $external = new self();
        $external->setUri($uri);
        $external->setCid($cid);

        return $external;
    }

    public static function createFromRecordResponse(RecordResponse $response): self
    {
        return self::create($response->getUri()->__toString(), $response->getCid());
    }
}
