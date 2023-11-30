<?php

declare(strict_types=1);

namespace potibm\Bluesky;

class BlueskyUri implements \Stringable
{
    private array $parts;

    public function __construct(
        private string $uri
    ) {
        // strip off the protocol
        $uri = substr($uri, 5);
        $this->parts = explode('/', $uri);
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getDID(): string
    {
        return $this->parts[0];
    }

    public function getNSID(): string
    {
        return $this->parts[1];
    }

    public function getRecord(): string
    {
        return $this->parts[2];
    }

    public function __toString(): string
    {
        return $this->uri;
    }
}
