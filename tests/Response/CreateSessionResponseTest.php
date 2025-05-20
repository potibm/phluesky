<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Response\CreateSessionResponse;

#[CoversClass(CreateSessionResponse::class)]
final class CreateSessionResponseTest extends TestCase
{
    public function testCreateValidObject(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->did = 'mydid';
        $sessionResponse->accessJwt = 'anAccessJwt';

        $session = new CreateSessionResponse($sessionResponse);
        $this->assertEquals('mydid', $session->getDid());
        $this->assertEquals('anAccessJwt', $session->getAuthToken());
    }

    public function testMissingDidValue(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->accessJwt = 'anAccessJwt';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        new CreateSessionResponse($sessionResponse);
    }

    public function testMissingAccessJwtValue(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->did = 'mydid';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        new CreateSessionResponse($sessionResponse);
    }
}
