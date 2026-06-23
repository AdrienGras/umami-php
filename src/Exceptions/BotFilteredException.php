<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Exceptions;

/**
 * Thrown when Umami's bot filter silently dropped a tracking hit.
 *
 * Umami answers `HTTP 200` with the body `{"beep":"boop"}` when the User-Agent
 * is detected as a bot (see send/route.ts). Saloon considers that response
 * successful, so {@see \AdrienGras\Umami\Responses\UmamiApiResponse} re-qualifies
 * it into this exception — the only case where a 2xx becomes an error.
 */
class BotFilteredException extends UmamiApiException
{
}
