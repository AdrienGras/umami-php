<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Contracts;

/**
 * Marker interface for requests that must NOT carry the reporting Bearer token.
 *
 * Umami's tracking endpoints (`/api/send`, `/api/batch`, `/api/record`) are
 * `skipAuth` server-side and would be rejected/altered by an Authorization
 * header. A request implementing this interface is excluded from the
 * connector's Bearer injection (see {@see \AdrienGras\Umami\UmamiApi}).
 */
interface SkipsAuth
{
}
