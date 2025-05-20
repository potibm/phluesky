<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

final class UploadBlobResponse implements UploadBlobResponseInterface
{
    use ResponseTrait;

    private string $mimeType;

    private int $size;

    private string $refLink;

    public function __construct(\stdClass $response)
    {
        $this->mimeType = (string) $this->getSessionProperty($response, 'mimeType');
        $this->size = (int) $this->getSessionProperty($response, 'size');
        $this->refLink = (string) $this->getSecondLevelSessionProperty($response, 'ref', '$link');
    }

    #[\Override]
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    #[\Override]
    public function getSize(): int
    {
        return $this->size;
    }

    #[\Override]
    public function getRefLink(): string
    {
        return $this->refLink;
    }

    #[\Override]
    public function jsonSerialize(): mixed
    {
        return [
            '$type' => 'blob',
            "mimeType" => $this->mimeType,
            "size" => $this->size,
            "ref" => [
                '$link' => $this->refLink,
            ],
        ];
    }
}
