<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Richtext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Feed\Post;
use potibm\Bluesky\Richtext\AbstractFacet;
use potibm\Bluesky\Richtext\FacetLink;
use potibm\Bluesky\Richtext\FacetMention;

#[CoversClass(FacetMention::class)]
#[CoversClass(AbstractFacet::class)]
class FacetMentionTest extends TestCase
{
    public function testCreate()
    {
        $link = FacetMention::create('mydid', 5, 16);

        $this->assertEquals('mydid', $link->getDid());
        $this->assertEquals(5, $link->getStart());
        $this->assertEquals(16, $link->getEnd());

        $this->assertEquals(
            ['index' =>
            [
                'byteStart' => 5,
                'byteEnd' => 16,
            ],
            'features' =>
                [

                    [
                        '$type' => 'app.bsky.richtext.facet#mention',
                        'did' => 'mydid',
                    ],

            ]],
            $link->jsonSerialize()
        );
    }

    public function testModifyUri()
    {
        $link = new FacetMention();
        $link->setDid('mynewdid');
        $this->assertEquals('mynewdid', $link->getDid());
    }
}
