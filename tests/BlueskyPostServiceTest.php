<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\BlueskyApi;
use potibm\Bluesky\BlueskyPostService;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Richtext\AbstractFacet;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;

#[CoversClass(BlueskyPostService::class)]
#[UsesClass(Post::class)]
#[UsesClass(AbstractFacet::class)]
#[UsesClass(FacetLink::class)]
#[UsesClass(FacetMention::class)]
#[UsesClass(Images::class)]
class BlueskyPostServiceTest extends TestCase
{
    private const SAMPLE = '✨ example mentioning @atproto.com ' .
        'to share the URL 👨‍❤️‍👨 https://en.wikipedia.org/wiki/CBOR.';

    private ?BlueskyPostService $postService = null;

    private ?Post $post = null;

    public function testMentionFacet()
    {
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

    public function testLinkFacet()
    {
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

    public function testLinkAndMentionFacets()
    {
        $resultPost = $this->postService->addFacetsFromMentionsAndLinks($this->post);

        $this->assertCount(2, $resultPost->getFacets());
        $this->assertInstanceOf(FacetMention::class, $resultPost->getFacets()[0]);
        $this->assertInstanceOf(FacetLink::class, $resultPost->getFacets()[1]);
    }

    public function testAddImge()
    {
        $resultPost = $this->postService->addImage($this->post, __FILE__, 'an alt text');

        $this->assertCount(1, $resultPost->getImages());
    }

    public function testAddImgeWithMissingImage()
    {
        $this->expectException(\Exception::class);
        $resultPost = $this->postService->addImage($this->post, __DIR__ . '/missingfile.png', 'an alt text');
    }

    public function setUp(): void
    {
        $this->post = Post::create(self::SAMPLE);

        $clientMock = $this->createMock(BlueskyApi::class);
        $clientMock->method('getDidForHandle')->with('atproto.com')
            ->willReturn('did:plc:ewvi7nxzyoun6zhxrhs64oiz');

        $this->postService = new BlueskyPostService($clientMock);
    }
}
