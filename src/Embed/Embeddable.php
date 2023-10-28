<?php

declare(strict_types=1);

namespace potibm\Bluesky\Embed;

interface Embeddable extends \JsonSerializable
{
    public function jsonSerialize(): mixed;
}
