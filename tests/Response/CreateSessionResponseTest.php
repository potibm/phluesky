<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\CreateSessionResponse;
use potibm\Bluesky\Richtext\AbstractFacet;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;

#[CoversClass(CreateSessionResponse::class)]
class CreateSessionResponseTest extends TestCase
{
    public function testCreateValidObject()
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->did = 'mydid';
        $sessionResponse->accessJwt = 'anAccessJwt';

        $session = new CreateSessionResponse($sessionResponse);
        $this->assertEquals('mydid', $session->getDid());
        $this->assertEquals('anAccessJwt', $session->getAuthToken());
    }

    public function testMissingDidValue()
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->accessJwt = 'anAccessJwt';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        $session = new CreateSessionResponse($sessionResponse);
    }

    public function testMissingAccessJwtValue()
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->did = 'mydid';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        $session = new CreateSessionResponse($sessionResponse);
    }
}
