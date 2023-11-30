<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\BlueskyApi;
use potibm\Bluesky\BlueskyApiInterface;
use potibm\Bluesky\BlueskyPostService;
use potibm\Bluesky\BlueskyUri;
use potibm\Bluesky\Embed\External;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Embed\Record;
use potibm\Bluesky\Exception\FileNotFoundException;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\RecordResponse;
use potibm\Bluesky\Response\ResponseTrait;
use potibm\Bluesky\Richtext\AbstractFacet;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;
use potibm\Bluesky\Test\Response\RecordResponseTest;

#[CoversClass(BlueskyPostService::class)]
#[UsesClass(Post::class)]
#[UsesClass(AbstractFacet::class)]
#[UsesClass(FacetLink::class)]
#[UsesClass(FacetMention::class)]
#[UsesClass(Images::class)]
#[UsesClass(External::class)]
#[UsesClass(BlueskyUri::class)]
#[UsesClass(Record::class)]
#[UsesClass(RecordResponse::class)]
#[UsesClass(ResponseTrait::class)]
class BlueskyPostServiceTest extends TestCase
{
    private const SAMPLE = '✨ example mentioning @atproto.com ' .
        'to share the URL 👨‍❤️‍👨 https://en.wikipedia.org/wiki/CBOR.';

    private BlueskyPostService $postService;

    /**
     * @var BlueskyApiInterface&MockObject
     */
    private BlueskyApiInterface $clientMock;

    private Post $post;

    public function testMentionFacet(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addFacetsFromMentions($this->post);

        $this->assertCount(1, $resultPost->getFacets());
        $this->assertInstanceOf(FacetMention::class, $resultPost->getFacets()[0]);
        /**
         * @var FacetMention $firstFacet
         */
        $firstFacet = $resultPost->getFacets()[0];
        $this->assertEquals('did:plc:ewvi7nxzyoun6zhxrhs64oiz', $firstFacet->getDid());
        $this->assertEquals(23, $firstFacet->getStart());
        $this->assertEquals(35, $firstFacet->getEnd());
    }

    public function testLinkFacet(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addFacetsFromLinks($this->post);

        $this->assertCount(1, $resultPost->getFacets());
        $this->assertInstanceOf(FacetLink::class, $resultPost->getFacets()[0]);
        /**
         * @var FacetLink $firstFacet
         */
        $firstFacet = $resultPost->getFacets()[0];
        $this->assertEquals('https://en.wikipedia.org/wiki/CBOR', $firstFacet->getUri());
        $this->assertEquals(74, $firstFacet->getStart());
        $this->assertEquals(108, $firstFacet->getEnd());
    }

    public function testLinkAndMentionFacets(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addFacetsFromMentionsAndLinks($this->post);

        $this->assertCount(2, $resultPost->getFacets());
        $this->assertInstanceOf(FacetMention::class, $resultPost->getFacets()[0]);
        $this->assertInstanceOf(FacetLink::class, $resultPost->getFacets()[1]);
    }

    public function testAddImage(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addImage($this->post, __FILE__, 'an alt text');

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Images::class, $embed);
        $this->assertCount(1, $embed);
    }

    public function testAddImgeWithMissingImage(): void
    {
        $this->expectException(FileNotFoundException::class);
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $this->postService->addImage($this->post, __DIR__ . '/missingfile.png', 'an alt text');
    }

    public function testAddExternal(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addWebsiteCard($this->post, 'https://example.com', 'title', 'desc', __FILE__);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(External::class, $embed);
        $this->assertNotNull($embed->getThumb());
    }

    public function testAddExternalWithoutThumb(): void
    {
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $resultPost = $this->postService->addWebsiteCard($this->post, 'https://example.com', 'title', 'desc');

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(External::class, $embed);
        $this->assertNull($embed->getThumb());
    }

    public function testAddExternalWithMissingFile(): void
    {
        $this->expectException(FileNotFoundException::class);
        /** @psalm-suppress PossiblyNullArgument, PossiblyNullReference */
        $this->postService->addWebsiteCard($this->post, 'https://example.com', 'title', 'desc', __DIR__ . '/missingfile.png');
    }

    public function testAddQuote(): void
    {
        $uri = 'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k4duaz5vfs2b';

        $this->clientMock
            ->method('getRecord')
            ->with(new BlueskyUri($uri))
            ->willReturn(new RecordResponse(RecordResponseTest::generateBlobResponse()));

        $resultPost = $this->postService->addQuote($this->post, $uri);

        $embed = $resultPost->getEmbed();
        $this->assertInstanceOf(Record::class, $embed);
        $this->assertEquals($uri, $embed->getUri());
    }

    public function testAddReplyIsRoot(): void
    {
        $uri = 'at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k4duaz5vfs2b';

        $this->clientMock
            ->method('getRecord')
            ->with(new BlueskyUri($uri))
            ->willReturn(new RecordResponse(RecordResponseTest::generateBlobResponse()));

        $resultPost = $this->postService->addReply($this->post, $uri);

        $json = $resultPost->jsonSerialize();
        $this->assertArrayHasKey('reply', $json);
        $this->assertArrayHasKey('root', $json['reply']);
        $this->assertArrayHasKey('uri', $json['reply']['root']);
        $this->assertEquals($uri, $json['reply']['root']['uri']);
        $this->assertArrayHasKey('parent', $json['reply']);
        $this->assertArrayHasKey('uri', $json['reply']['parent']);
        $this->assertEquals($uri, $json['reply']['parent']['uri']);
    }

    public function testAddReplyHasRoot(): void
    {
        $recordResponseRootBlob = RecordResponseTest::generateBlobResponse('at://did:plc:u5cwb2mwiv2bfq53cjufe6yn/app.bsky.feed.post/3k4duaz5vroot');
        $recordResponseRoot = new RecordResponse($recordResponseRootBlob);

        $recordResponseParentBlob = RecordResponseTest::generateBlobResponse(null, $recordResponseRootBlob);
        $recordResponseParent = new RecordResponse($recordResponseParentBlob);

        $this->clientMock
            ->method('getRecord')
            ->willReturn($recordResponseParent, $recordResponseRoot);

        $resultPost = $this->postService->addReply($this->post, $recordResponseParentBlob->uri);

        $json = $resultPost->jsonSerialize();
        $this->assertArrayHasKey('reply', $json);
        $this->assertArrayHasKey('root', $json['reply']);
        $this->assertArrayHasKey('uri', $json['reply']['root']);
        $this->assertEquals($recordResponseRootBlob->uri, $json['reply']['root']['uri']);
        $this->assertArrayHasKey('parent', $json['reply']);
        $this->assertArrayHasKey('uri', $json['reply']['parent']);
        $this->assertEquals($recordResponseParentBlob->uri, $json['reply']['parent']['uri']);
    }

    public function setUp(): void
    {
        $this->post = Post::create(self::SAMPLE);

        $this->clientMock = $this->createMock(BlueskyApi::class);
        $this->clientMock->method('getDidForHandle')->with('atproto.com')
            ->willReturn('did:plc:ewvi7nxzyoun6zhxrhs64oiz');

        $this->postService = new BlueskyPostService($this->clientMock);
    }
}
