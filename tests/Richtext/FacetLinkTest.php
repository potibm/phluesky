<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Richtext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Richtext\AbstractFacet;
use potibm\Bluesky\Richtext\FacetLink;

#[CoversClass(FacetLink::class)]
#[CoversClass(AbstractFacet::class)]
class FacetLinkTest extends TestCase
{
    public function testCreate(): void
    {
        $link = FacetLink::create('myuri', 5, 16);

        $this->assertEquals('myuri', $link->getUri());
        $this->assertEquals(5, $link->getStart());
        $this->assertEquals(16, $link->getEnd());

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
                            '$type' => 'app.bsky.richtext.facet#link',
                            'uri' => 'myuri',
                        ],

                    ],
            ],
            $link->jsonSerialize()
        );
    }

    public function testModifyUri(): void
    {
        $link = new FacetLink();
        $link->setUri('mynewuri');
        $this->assertEquals('mynewuri', $link->getUri());
    }
}
