<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Responses;

use AdrienGras\Umami\Exceptions\BotFilteredException;
use AdrienGras\Umami\Exceptions\UmamiApiException;
use Saloon\Http\Response;
use Throwable;

/**
 * Custom Saloon response for the Umami API.
 *
 * Two responsibilities on top of Saloon's defaults:
 *  1. Re-qualify the bot-filtered 200 (`{"beep":"boop"}`) as a failure so that
 *     {@see \Saloon\Traits\Plugins\AlwaysThrowOnErrors} throws on it.
 *  2. Map every failure to a typed {@see UmamiApiException}
 *     ({@see BotFilteredException} for the bot case).
 */
class UmamiApiResponse extends Response
{
    /**
     * Whether Umami's bot filter silently dropped the hit: HTTP 200 with the
     * literal body `{"beep":"boop"}` (see send/route.ts & record/route.ts).
     */
    public function isBotFiltered(): bool
    {
        if ($this->status() !== 200) {
            return false;
        }

        $body = $this->body();

        // Cheap guard before decoding — the bot body is short and literal.
        if (!str_contains($body, 'beep')) {
            return false;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) && ($decoded['beep'] ?? null) === 'boop';
    }

    public function failed(): bool
    {
        return parent::failed() || $this->isBotFiltered();
    }

    protected function createException(): Throwable
    {
        $previous = $this->getSenderException();

        if ($this->isBotFiltered()) {
            return new BotFilteredException($this, 'Hit dropped by Umami bot filter (beep/boop).', 0, $previous);
        }

        return new UmamiApiException($this, null, 0, $previous);
    }
}
