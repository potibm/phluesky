<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

use potibm\Bluesky\Exception\InvalidPayloadException;

final class CreateSessionResponse
{
    use ResponseTrait;

    private string $authToken;

    private string $refreshToken;

    private string $did;

    private string $handle;

    public function __construct(\stdClass $sessionData)
    {
        $this->authToken = $this->getSessionProperty($sessionData, 'accessJwt');
        $this->did = $this->getSessionProperty($sessionData, 'did');
        $this->refreshToken = property_exists($sessionData, 'refreshJwt') ? (string) $sessionData->refreshJwt : '';
        $this->handle = property_exists($sessionData, 'handle') ? (string) $sessionData->handle : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['accessJwt'], $data['did']) || ! is_string($data['accessJwt']) || ! is_string($data['did'])) {
            throw new InvalidPayloadException('Cached session data is missing "accessJwt" or "did"');
        }

        $sessionData = new \stdClass();
        $sessionData->accessJwt = $data['accessJwt'];
        $sessionData->did = $data['did'];
        $sessionData->refreshJwt = isset($data['refreshJwt']) && is_string($data['refreshJwt']) ? $data['refreshJwt'] : '';
        $sessionData->handle = isset($data['handle']) && is_string($data['handle']) ? $data['handle'] : '';

        return new self($sessionData);
    }

    /**
     * @return array{accessJwt: string, did: string, refreshJwt: string, handle: string}
     */
    public function toArray(): array
    {
        return [
            'accessJwt' => $this->authToken,
            'did' => $this->did,
            'refreshJwt' => $this->refreshToken,
            'handle' => $this->handle,
        ];
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getHandle(): string
    {
        return $this->handle;
    }
}
