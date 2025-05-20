<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\BlueskyUri;

#[CoversClass(BlueskyUri::class)]
final class BlueskyUriTest extends TestCase
{
    public function testSimple(): void
    {
        $uri = 'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k43tv4rft22g';
        $blueskyUri = new BlueskyUri($uri);
        $this->assertEquals($uri, $blueskyUri->getUri());
        $this->assertEquals($uri, (string) $blueskyUri);
        $this->assertEquals('did:plc:u5cwb2mwiv2bfq53cjufe6yn', $blueskyUri->getDID());
        $this->assertEquals('app.bsky.feed.post', $blueskyUri->getNSID());
        $this->assertEquals('3k43tv4rft22g', $blueskyUri->getRecord());
    }
}
