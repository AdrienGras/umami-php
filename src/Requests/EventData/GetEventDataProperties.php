<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\EventData;

use AdrienGras\Umami\Requests\EventData\Impl\AbstractEventDataRequest;

/** `GET /api/websites/{id}/event-data/properties` — event property names (Bearer). */
class GetEventDataProperties extends AbstractEventDataRequest
{
    protected function path(): string
    {
        return '/properties';
    }
}
