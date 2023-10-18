<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

use potibm\Bluesky\Exception\InvalidPayloadException;

trait ResponseTrait
{
    private function getSessionProperty(\stdClass $sessionData, string $property): string
    {
        if (!property_exists($sessionData, $property)) {
            throw new InvalidPayloadException('JSON response does not contain "' . $property . '" property');
        }

        return $sessionData->$property;
    }
}
