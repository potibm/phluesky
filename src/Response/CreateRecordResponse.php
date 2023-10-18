<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

class CreateRecordResponse
{
    use ResponseTrait;

    private string $uri;

    private string $cid;

    public function __construct(\stdClass $response)
    {
        $this->uri = $this->getSessionProperty($response, 'uri');
        $this->cid = $this->getSessionProperty($response, 'cid');
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getCid(): string
    {
        return $this->cid;
    }
}
