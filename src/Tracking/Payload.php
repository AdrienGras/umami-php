<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tracking;

/**
 * Immutable description of a single Umami tracking hit payload.
 *
 * Mirrors the `payload` object of `POST /api/send` (send/route.ts schema).
 * Every field is optional here; domain rules (exactly one of
 * website/link/pixel, etc.) are enforced by the TrackingEntrypoint, not by this
 * value object. {@see toArray()} drops null fields so optional values are never
 * sent.
 *
 * Note: for tracking, set {@see $userAgent} to the *visitor's* User-Agent.
 * Otherwise Umami falls back to the connector's UA, which its bot filter
 * rejects (HTTP 200 `{"beep":"boop"}` → BotFilteredException).
 *
 * @phpstan-type DataArray array<string, mixed>
 */
final readonly class Payload
{
    /**
     * @param DataArray|null $data event/identify custom properties
     */
    public function __construct(
        public ?string $website = null,
        public ?string $link = null,
        public ?string $pixel = null,
        public ?array $data = null,
        public ?string $hostname = null,
        public ?string $language = null,
        public ?string $referrer = null,
        public ?string $screen = null,
        public ?string $title = null,
        public ?string $url = null,
        public ?string $name = null,
        public ?string $tag = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?int $timestamp = null,
        public ?string $id = null,
        public ?string $browser = null,
        public ?string $os = null,
        public ?string $device = null,
        public ?float $lcp = null,
        public ?float $inp = null,
        public ?float $cls = null,
        public ?float $fcp = null,
        public ?float $ttfb = null,
    ) {
    }

    /**
     * Serialise to the API shape, omitting every null field.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $fields = [
            'website' => $this->website,
            'link' => $this->link,
            'pixel' => $this->pixel,
            'data' => $this->data,
            'hostname' => $this->hostname,
            'language' => $this->language,
            'referrer' => $this->referrer,
            'screen' => $this->screen,
            'title' => $this->title,
            'url' => $this->url,
            'name' => $this->name,
            'tag' => $this->tag,
            'ip' => $this->ip,
            'userAgent' => $this->userAgent,
            'timestamp' => $this->timestamp,
            'id' => $this->id,
            'browser' => $this->browser,
            'os' => $this->os,
            'device' => $this->device,
            'lcp' => $this->lcp,
            'inp' => $this->inp,
            'cls' => $this->cls,
            'fcp' => $this->fcp,
            'ttfb' => $this->ttfb,
        ];

        return array_filter($fields, static fn (mixed $value): bool => null !== $value);
    }
}
