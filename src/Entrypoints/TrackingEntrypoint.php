<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Entrypoints;

use AdrienGras\Umami\Entrypoints\Impl\AbstractEntrypoint;
use AdrienGras\Umami\Enums\CollectionType;
use AdrienGras\Umami\Requests\Tracking\SendBatch;
use AdrienGras\Umami\Requests\Tracking\SendHit;
use AdrienGras\Umami\Tracking\Payload;
use InvalidArgumentException;
use Saloon\Http\Response;

/**
 * Tracking façade — `POST /api/send` and `POST /api/batch`.
 *
 * Turns clean arguments into hits, enforces the input rules (exactly one of
 * website/link/pixel, non-empty event name / distinct id), then sends a dumb
 * request. These endpoints are skipAuth, so the requests carry no Bearer.
 *
 * Reminder: pass the visitor's `userAgent` for real tracking — otherwise Umami
 * falls back to the library UA and its bot filter drops the hit
 * (BotFilteredException).
 */
readonly class TrackingEntrypoint extends AbstractEntrypoint
{
    /**
     * Send a single hit. Defaults to a page/event hit; pass
     * {@see CollectionType::Identify} or {@see CollectionType::Performance} for
     * the other collection types.
     */
    public function send(Payload $payload, CollectionType $type = CollectionType::Event): Response
    {
        $this->assertExactlyOneTarget($payload);

        return $this->api->send(new SendHit($type->value, $payload->toArray()));
    }

    /**
     * Send several hits in one request. All hits share the same collection type.
     *
     * @param array<int, Payload> $payloads
     */
    public function batch(array $payloads, CollectionType $type = CollectionType::Event): Response
    {
        if ([] === $payloads) {
            throw new InvalidArgumentException('batch() requires at least one payload.');
        }

        $hits = [];

        foreach ($payloads as $payload) {
            $this->assertExactlyOneTarget($payload);

            $hits[] = [
                'type' => $type->value,
                'payload' => $payload->toArray(),
            ];
        }

        return $this->api->send(new SendBatch($hits));
    }

    /**
     * Track a page view (an event hit without a custom event name).
     */
    public function pageview(
        string $websiteId,
        ?string $url = null,
        ?string $title = null,
        ?string $referrer = null,
        ?string $hostname = null,
        ?string $userAgent = null,
    ): Response {
        return $this->send(new Payload(
            website: trim($websiteId),
            referrer: $referrer,
            hostname: $hostname,
            title: $title,
            url: $url,
            userAgent: $userAgent,
        ));
    }

    /**
     * Track a custom event (a named hit, optionally with properties).
     *
     * @param array<string, mixed>|null $data
     */
    public function event(
        string $websiteId,
        string $name,
        ?array $data = null,
        ?string $url = null,
        ?string $userAgent = null,
    ): Response {
        $name = trim($name);

        if ('' === $name) {
            throw new InvalidArgumentException('event() name must not be empty.');
        }

        return $this->send(new Payload(
            website: trim($websiteId),
            data: $data,
            url: $url,
            name: $name,
            userAgent: $userAgent,
        ));
    }

    /**
     * Attach a stable identity (distinct id) to the current session.
     *
     * @param array<string, mixed>|null $data
     */
    public function identify(
        string $websiteId,
        string $distinctId,
        ?array $data = null,
        ?string $userAgent = null,
    ): Response {
        $distinctId = trim($distinctId);

        if ('' === $distinctId) {
            throw new InvalidArgumentException('identify() distinctId must not be empty.');
        }

        return $this->send(
            new Payload(
                website: trim($websiteId),
                data: $data,
                userAgent: $userAgent,
                id: $distinctId,
            ),
            CollectionType::Identify,
        );
    }

    /**
     * Umami requires exactly one of website / link / pixel on a payload.
     */
    private function assertExactlyOneTarget(Payload $payload): void
    {
        $targets = array_filter(
            [$payload->website, $payload->link, $payload->pixel],
            static fn (?string $value): bool => null !== $value && '' !== $value,
        );

        if (1 !== count($targets)) {
            throw new InvalidArgumentException(
                'Exactly one of website, link or pixel must be provided.',
            );
        }
    }
}
