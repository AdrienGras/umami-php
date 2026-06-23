<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Tests\Unit\Website;

use AdrienGras\Umami\Enums\MaskLevel;
use AdrienGras\Umami\Website\ReplayConfig;
use PHPUnit\Framework\TestCase;

final class ReplayConfigTest extends TestCase
{
    public function testToArrayOmitsNulls(): void
    {
        $config = new ReplayConfig(sampleRate: 0.5, maskLevel: MaskLevel::Strict);

        self::assertSame(
            ['sampleRate' => 0.5, 'maskLevel' => 'strict'],
            $config->toArray(),
        );
    }

    public function testToArrayFullPayload(): void
    {
        $config = new ReplayConfig(
            sampleRate: 1.0,
            maskLevel: MaskLevel::Moderate,
            maxDuration: 3600,
            blockSelector: '.private',
        );

        self::assertSame(
            [
                'sampleRate' => 1.0,
                'maskLevel' => 'moderate',
                'maxDuration' => 3600,
                'blockSelector' => '.private',
            ],
            $config->toArray(),
        );
    }

    public function testToArrayEmptyWhenAllNull(): void
    {
        self::assertSame([], (new ReplayConfig())->toArray());
    }
}
