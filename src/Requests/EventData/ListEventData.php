<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\EventData;

use AdrienGras\Umami\Requests\EventData\Impl\AbstractEventDataRequest;

/** `GET /api/websites/{id}/event-data` — paginated event-data records (Bearer). */
class ListEventData extends AbstractEventDataRequest
{
}
