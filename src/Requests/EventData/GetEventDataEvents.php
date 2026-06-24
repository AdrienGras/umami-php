<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\EventData;

use AdrienGras\Umami\Requests\EventData\Impl\AbstractEventDataRequest;

/** `GET /api/websites/{id}/event-data/events` — property breakdown for an event (Bearer). */
class GetEventDataEvents extends AbstractEventDataRequest
{
    protected function path(): string
    {
        return '/events';
    }
}
