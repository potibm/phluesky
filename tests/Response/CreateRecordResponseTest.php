<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\CreateRecordResponse;
use potibm\Bluesky\Response\CreateSessionResponse;
use potibm\Bluesky\Richtext\AbstractFacet;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;

#[CoversClass(CreateRecordResponse::class)]
class CreateRecordResponseTest extends TestCase
{
    public function testCreateValidObject()
    {
        $createResponse = new \stdClass();
        $createResponse->uri = 'an-uri';
        $createResponse->cid = 'anothercid';

        $create = new CreateRecordResponse($createResponse);
        $this->assertEquals('an-uri', $create->getUri());
        $this->assertEquals('anothercid', $create->getCid());
    }

    public function testMissingCidValue()
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->uri = 'an-uri';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        $session = new CreateRecordResponse($sessionResponse);
    }

    public function testMissingUriValue()
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->cid = 'anothercid';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        $session = new CreateRecordResponse($sessionResponse);
    }
}
