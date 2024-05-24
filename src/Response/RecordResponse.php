<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

use potibm\Bluesky\BlueskyUri;

class RecordResponse
{
    use ResponseTrait;

    private BlueskyUri $uri;

    private string $cid;

    private ?\stdClass $value = null;

    public function __construct(\stdClass $response)
    {
        $this->uri = new BlueskyUri((string) $this->getSessionProperty($response, 'uri'));
        $this->cid = $this->getSessionProperty($response, 'cid');
        if (property_exists($response, 'value')) {
            $this->value = $this->getSessionProperty($response, 'value');
        }
    }

    public function getUri(): BlueskyUri
    {
        return $this->uri;
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function getReplyRoot(): ?RecordResponse
    {
        $root = $this->value?->reply?->root;
        if ($root !== null) {
            return new RecordResponse($root);
        }
        return null;
    }
}
