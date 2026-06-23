<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Website;

use AdrienGras\Umami\Enums\MaskLevel;

/**
 * Session replay configuration for a website update.
 *
 * Input value object only — no guards live here (transport-only pattern).
 * {@see toArray()} omits null fields so optional values are never sent.
 */
final readonly class ReplayConfig
{
    public function __construct(
        public ?float $sampleRate = null,
        public ?MaskLevel $maskLevel = null,
        public ?int $maxDuration = null,
        public ?string $blockSelector = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $config = [];

        if (null !== $this->sampleRate) {
            $config['sampleRate'] = $this->sampleRate;
        }

        if (null !== $this->maskLevel) {
            $config['maskLevel'] = $this->maskLevel->value;
        }

        if (null !== $this->maxDuration) {
            $config['maxDuration'] = $this->maxDuration;
        }

        if (null !== $this->blockSelector) {
            $config['blockSelector'] = $this->blockSelector;
        }

        return $config;
    }
}
