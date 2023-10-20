<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Response\CreateRecordResponse;

#[CoversClass(CreateRecordResponse::class)]
class CreateRecordResponseTest extends TestCase
{
    public function testCreateValidObject(): void
    {
        $createResponse = new \stdClass();
        $createResponse->uri = 'an-uri';
        $createResponse->cid = 'anothercid';

        $create = new CreateRecordResponse($createResponse);
        $this->assertEquals('an-uri', $create->getUri());
        $this->assertEquals('anothercid', $create->getCid());
    }

    public function testMissingCidValue(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->uri = 'an-uri';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        new CreateRecordResponse($sessionResponse);
    }

    public function testMissingUriValue(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->cid = 'anothercid';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        new CreateRecordResponse($sessionResponse);
    }
}
