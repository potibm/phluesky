<?php

declare(strict_types=1);

namespace Richtext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Richtext\AbstractFacet;
use potibm\Bluesky\Richtext\FacetTag;

#[CoversClass(FacetTag::class)]
#[CoversClass(AbstractFacet::class)]
class FaceTagTest extends TestCase
{
    public function testCreate(): void
    {
        $tag = FacetTag::create('mytag', 5, 16);

        $this->assertEquals('mytag', $tag->getTag());
        $this->assertEquals(5, $tag->getStart());
        $this->assertEquals(16, $tag->getEnd());

        $this->assertEquals(
            [
                'index' =>
                            [
                                'byteStart' => 5,
                                'byteEnd' => 16,
                            ],
                'features' =>
                    [

                        [
                            '$type' => 'app.bsky.richtext.facet#tag',
                            'tag' => 'mytag',
                        ],

                    ],
            ],
            $tag->jsonSerialize()
        );
    }

    public function testModifyTag(): void
    {
        $tag = new FacetTag();
        $tag->setTag('mynewtag');
        $this->assertEquals('mynewtag', $tag->getTag());
    }
}
