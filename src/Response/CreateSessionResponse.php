<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

class CreateSessionResponse
{
    use ResponseTrait;

    private string $authToken;

    private string $did;

    public function __construct(\stdClass $sessionData)
    {
        $this->authToken = $this->getSessionProperty($sessionData, 'accessJwt');
        $this->did = $this->getSessionProperty($sessionData, 'did');
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getDid(): string
    {
        return $this->did;
    }
}
