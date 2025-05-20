<?php

declare(strict_types=1);

namespace potibm\Bluesky\Test\Embed;

use PHPUnit\Framework\TestCase;
use potibm\Bluesky\Embed\AspectRatio;

#[\PHPUnit\Framework\Attributes\CoversClass(AspectRatio::class)]
final class AspectRatioTest extends TestCase
{
    public function testAspectRatioInitialization(): void
    {
        $ar = new AspectRatio(800, 600);

        $this->assertEquals(800, $ar->width);
        $this->assertEquals(600, $ar->height);
    }

    public function testToArray(): void
    {
        $ar = new AspectRatio(1920, 1080);
        $this->assertEquals([
            'width' => 1920,
            'height' => 1080,
        ], $ar->toArray());
    }
}
