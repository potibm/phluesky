<?php

declare(strict_types=1);

namespace potibm\Bluesky\Embed;

interface Embeddable extends \JsonSerializable
{
    #[\Override]
    public function jsonSerialize(): mixed;
}
