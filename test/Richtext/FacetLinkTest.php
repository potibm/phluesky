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

#[CoversClass(FacetLink::class)]
#[CoversClass(AbstractFacet::class)]
class FacetLinkTest extends TestCase
{
    public function testCreate()
    {
        $link = FacetLink::create('myuri', 5, 16);

        $this->assertEquals('myuri', $link->getUri());
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
                        '$type' => 'app.bsky.richtext.facet#link',
                        'uri' => 'myuri',
                    ],

            ]],
            $link->jsonSerialize()
        );
    }

    public function testModifyUri()
    {
        $link = new FacetLink();
        $link->setUri('mynewuri');
        $this->assertEquals('mynewuri', $link->getUri());
    }
}
