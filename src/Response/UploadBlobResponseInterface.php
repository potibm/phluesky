<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

use JsonSerializable;

interface UploadBlobResponseInterface extends JsonSerializable
{
    public function getMimeType(): string;

    public function getSize(): int;

    public function getRefLink(): string;
}
