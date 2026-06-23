<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Stats;

use AdrienGras\Umami\Requests\Stats\Impl\AbstractStatRequest;

/** `GET /api/websites/{id}/stats` */
class GetStats extends AbstractStatRequest
{
    protected function segment(): string
    {
        return 'stats';
    }
}
