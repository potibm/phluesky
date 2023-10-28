<?php

declare(strict_types=1);

namespace potibm\Bluesky\Response;

use potibm\Bluesky\Exception\InvalidPayloadException;

trait ResponseTrait
{
    private function getSessionProperty(\stdClass $sessionData, string $property): mixed
    {
        if (! property_exists($sessionData, $property)) {
            throw new InvalidPayloadException('JSON response does not contain "' . $property . '" property');
        }

        return $sessionData->$property;
    }

    private function getSecondLevelSessionProperty(\stdClass $sessionData, string $mainProperty, string $property): mixed
    {
        if (! property_exists($sessionData, $mainProperty) || ! $sessionData->$mainProperty instanceof \stdClass) {
            throw new InvalidPayloadException('JSON response does not contain "' . $mainProperty . '" property');
        }

        return $this->getSessionProperty($sessionData->$mainProperty, $property);
    }
}
