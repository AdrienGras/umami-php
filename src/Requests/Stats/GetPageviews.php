<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Stats;

use AdrienGras\Umami\Requests\Stats\Impl\AbstractStatRequest;

/** `GET /api/websites/{id}/pageviews` */
class GetPageviews extends AbstractStatRequest
{
    protected function segment(): string
    {
        return 'pageviews';
    }
}
