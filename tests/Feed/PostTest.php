<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Feed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Embed\Images;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Response\ResponseTrait;
use potibm\Bluesky\Response\UploadBlobResponse;
use potibm\Bluesky\Richtext\AbstractFacet;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;
use potibm\Bluesky\Test\Response\UploadBlobResponseTest;

#[CoversClass(Post::class)]
#[UsesClass(AbstractFacet::class)]
#[UsesClass(FacetLink::class)]
#[UsesClass(FacetMention::class)]
#[UsesClass(Images::class)]
#[UsesClass(ResponseTrait::class)]
#[UsesClass(UploadBlobResponse::class)]
class PostTest extends TestCase
{
    public function testMinimalToJson()
    {
        $text = 'Hello world';
        $now = new \DateTimeImmutable('2023-08-07 05:46:14.423045');

        $post = new Post();
        $post->setText($text);
        $post->setCreatedAt($now);

        $this->assertEquals($text, $post->getText());
        $this->assertEquals($now->getTimestamp(), $post->getCreatedAt()->getTimestamp());

        $jsonOutput = [
            '$type' => "app.bsky.feed.post",
            'text' => "Hello world",
            'createdAt' => "2023-08-07T05:46:14.423045Z",
        ];

        $this->assertEquals($jsonOutput, $post->jsonSerialize());
    }

    public function testLanguages()
    {
        $post = Post::create('Hello world');
        $post->setLangs(['en', 'fr']);

        $this->assertEquals(['en', 'fr'], $post->getLangs());

        $jsonOutput = $post->jsonSerialize();
        $this->arrayHasKey('langs', $jsonOutput);
        $this->assertEquals(['en', 'fr'], $jsonOutput['langs']);
    }

    public function testAddedMention()
    {
        $mention = FacetMention::create('my:did', 0, 11);

        $post = Post::create('Hello world');
        $post->addFacet($mention);

        $jsonOutput = $post->jsonSerialize();
        $this->arrayHasKey('facets ', $jsonOutput);
        $this->assertCount(1, $jsonOutput['facets']);
        $this->assertEquals($mention->jsonSerialize(), $jsonOutput['facets'][0]);
    }

    public function testAddedLink()
    {
        $link = FacetLink::create('myuri', 5, 16);

        $post = Post::create('Hello world');
        $post->addFacet($link);

        $jsonOutput = $post->jsonSerialize();
        $this->arrayHasKey('facets ', $jsonOutput);
        $this->assertCount(1, $jsonOutput['facets']);
        $this->assertEquals($link->jsonSerialize(), $jsonOutput['facets'][0]);
    }

    public function testRemoveAllFacets()
    {
        $post = Post::create('Hello world');
        $post->addFacet(FacetLink::create('myuri', 5, 16));
        $this->assertCount(1, $post->getFacets());
        $post->removeAllFacets();
        $this->assertCount(0, $post->getFacets());
    }

    public function testAddedImage()
    {
        $post = Post::create('Hello world');
        $post->getImages()->addImage(
            new UploadBlobResponse(UploadBlobResponseTest::generateBlobResponse()),
            'an alt text'
        );

        $this->assertCount(1, $post->getImages());

        $json = $post->jsonSerialize();
        $this->assertArrayHasKey('embed', $json);
        $this->assertArrayHasKey('$type', $json['embed']);
        $this->assertEquals('app.bsky.embed.images', $json['embed']['$type']);
        $this->assertArrayHasKey('images', $json['embed']);
        $this->assertCount(1, $json['embed']['images']);
    }
}
