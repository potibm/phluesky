<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Embed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\BlueskyUri;
use potibm\Bluesky\Embed\Record;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Response\ResponseTrait;

#[CoversClass(Record::class)]
#[UsesClass(BlueskyUri::class)]
#[UsesClass(RecordResponse::class)]
#[UsesClass(ResponseTrait::class)]
class RecordTest extends TestCase
{
    public function testCreate(): void
    {
        $external = Record::create('at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k44deefqdk2g', 'bafyreiecx6dujwoeqpdzl27w67z4h46hyklk3an4i4cvvmioaqb2qbyo5u');

        $this->assertInstanceOf(Record::class, $external);
        $this->assertEquals('at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k44deefqdk2g', $external->getUri());
        $this->assertEquals('bafyreiecx6dujwoeqpdzl27w67z4h46hyklk3an4i4cvvmioaqb2qbyo5u', $external->getCid());

        $this->assertEquals([
            '$type' => 'app.bsky.embed.record',
            'record' => [
                'uri' => 'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k44deefqdk2g',
                'cid' => 'bafyreiecx6dujwoeqpdzl27w67z4h46hyklk3an4i4cvvmioaqb2qbyo5u',
            ],
        ], $external->jsonSerialize());
    }

    public function testCreateFromRecordResponse(): void
    {
        $resp = new \stdClass();
        $resp->uri = 'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k44deefqdk2g';
        $resp->cid = 'bafyreiecx6dujwoeqpdzl27w67z4h46hyklk3an4i4cvvmioaqb2qbyo5u';

        $response = new RecordResponse($resp);
        $record = Record::createFromRecordResponse($response);
        $this->assertInstanceOf(Record::class, $record);
        $this->assertEquals('at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k44deefqdk2g', $record->getUri());
        $this->assertEquals('bafyreiecx6dujwoeqpdzl27w67z4h46hyklk3an4i4cvvmioaqb2qbyo5u', $record->getCid());
    }
}
