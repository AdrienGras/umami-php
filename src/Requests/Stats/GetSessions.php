<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Stats;

use AdrienGras\Umami\Requests\Stats\Impl\AbstractStatRequest;

/** `GET /api/websites/{id}/sessions` */
class GetSessions extends AbstractStatRequest
{
    protected function segment(): string
    {
        return 'sessions';
    }
}
