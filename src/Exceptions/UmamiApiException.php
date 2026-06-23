<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Exceptions;

use Saloon\Exceptions\Request\RequestException;

/**
 * Base exception for every failed Umami API call.
 *
 * Thrown automatically by {@see \AdrienGras\Umami\Responses\UmamiApiResponse}
 * whenever a response is considered failed (non-2xx, or the bot-filtered 200 —
 * see {@see BotFilteredException}).
 */
class UmamiApiException extends RequestException
{
    /**
     * HTTP status code of the failed response.
     */
    public function getStatusCode(): int
    {
        return $this->getStatus();
    }

    /**
     * Umami's machine-readable error code, when present.
     *
     * Umami error bodies look like `{"error":{"message","code","status"}}`
     * (see lib/response.ts). Returns null for non-standard bodies (e.g. the
     * bot 200, or a zod validation tree).
     */
    public function errorCode(): ?string
    {
        $error = $this->getResponse()->json('error');

        if (is_array($error) && isset($error['code']) && is_scalar($error['code'])) {
            return (string) $error['code'];
        }

        return null;
    }
}
