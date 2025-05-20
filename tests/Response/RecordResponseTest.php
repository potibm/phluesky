<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\BlueskyUri;
use potibm\Bluesky\Response\RecordResponse;

#[CoversClass(RecordResponse::class)]
#[UsesClass(BlueskyUri::class)]
final class RecordResponseTest extends TestCase
{
    public function testCreateValidObject(): void
    {
        $createResponse = new \stdClass();
        $createResponse->uri = 'an-uri';
        $createResponse->cid = 'anothercid';

        $create = new RecordResponse($createResponse);
        $this->assertEquals('an-uri', $create->getUri());
        $this->assertEquals('anothercid', $create->getCid());
        $this->assertNull($create->getReplyRoot());
    }

    public function testMissingCidValue(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->uri = 'an-uri';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        new RecordResponse($sessionResponse);
    }

    public function testMissingUriValue(): void
    {
        $sessionResponse = new \stdClass();
        $sessionResponse->cid = 'anothercid';

        $this->expectException(\potibm\Bluesky\Exception\InvalidPayloadException::class);
        new RecordResponse($sessionResponse);
    }

    public function testWithRootValue(): void
    {
        $rootBlob = $this->generateBlobResponse('at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/root');
        $resposeBlob = $this->generateBlobResponse(null, $rootBlob);

        $create = new RecordResponse($resposeBlob);
        $this->assertInstanceOf(RecordResponse::class, $create->getReplyRoot());
        $this->assertEquals($rootBlob->uri, $create->getReplyRoot()?->getUri()?->getUri());
    }

    public static function generateBlobResponse(?string $uri = null, ?\stdClass $rootBlob = null): \stdClass
    {
        if ($uri === null) {
            $uri = 'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k4duaz5vfs2b';
        }

        $response = new \stdClass();
        $response->uri = $uri;
        $response->cid = 'bafyreibjifzpqj6o6wcq3hejh7y4z4z2vmiklkvykc57tw3pcbx3kxifpm';

        if ($rootBlob) {
            $response->value = new \stdClass();
            $response->value->reply = new \stdClass();
            $response->value->reply->root = $rootBlob;
        }

        return $response;
    }
}
