<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Exception\InvalidPayloadException;
use potibm\Bluesky\Response\CreateSessionResponse;

#[CoversClass(CreateSessionResponse::class)]
#[UsesClass(InvalidPayloadException::class)]
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

    public function testCreateValidObjectWithAllFields(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->did = 'mydid';
        $sessionResponse->accessJwt = 'anAccessJwt';
        $sessionResponse->refreshJwt = 'aRefreshJwt';
        $sessionResponse->handle = 'my.handle';

        $session = new CreateSessionResponse($sessionResponse);
        $this->assertSame('aRefreshJwt', $session->getRefreshToken());
        $this->assertSame('my.handle', $session->getHandle());
    }

    public function testOptionalFieldsDefaultToEmptyString(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->did = 'mydid';
        $sessionResponse->accessJwt = 'anAccessJwt';

        $session = new CreateSessionResponse($sessionResponse);
        $this->assertSame('', $session->getRefreshToken());
        $this->assertSame('', $session->getHandle());
    }

    public function testToArrayAndFromArrayRoundtrip(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->did = 'mydid';
        $sessionResponse->accessJwt = 'anAccessJwt';
        $sessionResponse->refreshJwt = 'aRefreshJwt';
        $sessionResponse->handle = 'my.handle';

        $session = new CreateSessionResponse($sessionResponse);
        $restored = CreateSessionResponse::fromArray($session->toArray());

        $this->assertSame($session->toArray(), $restored->toArray());
    }

    public function testFromArrayWithoutOptionalFields(): void
    {
        $session = CreateSessionResponse::fromArray([
            'accessJwt' => 'anAccessJwt',
            'did' => 'mydid',
        ]);

        $this->assertSame('anAccessJwt', $session->getAuthToken());
        $this->assertSame('mydid', $session->getDid());
        $this->assertSame('', $session->getRefreshToken());
        $this->assertSame('', $session->getHandle());
    }

    public function testFromArrayWithMissingRequiredFieldsThrows(): void
    {
        $this->expectException(InvalidPayloadException::class);
        CreateSessionResponse::fromArray([
            'did' => 'mydid',
        ]);
    }

    public function testMissingDidValue(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->accessJwt = 'anAccessJwt';

        $this->expectException(InvalidPayloadException::class);
        new CreateSessionResponse($sessionResponse);
    }

    public function testMissingAccessJwtValue(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->did = 'mydid';

        $this->expectException(InvalidPayloadException::class);
        new CreateSessionResponse($sessionResponse);
    }
}
