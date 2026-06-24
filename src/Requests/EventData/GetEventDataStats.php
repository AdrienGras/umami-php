<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\EventData;

use AdrienGras\Umami\Requests\EventData\Impl\AbstractEventDataRequest;

/** `GET /api/websites/{id}/event-data/stats` — aggregate event-data counts (Bearer). */
class GetEventDataStats extends AbstractEventDataRequest
{
    protected function path(): string
    {
        return '/stats';
    }
}
