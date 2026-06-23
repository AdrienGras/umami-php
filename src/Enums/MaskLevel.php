<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Enums;

/**
 * Session replay masking level (website replayConfig).
 *
 * Mirrors the `maskLevel` enum of Umami v3.1.0
 * (`websites/[websiteId]/route.ts` replayConfig schema).
 */
enum MaskLevel: string
{
    case Strict = 'strict';
    case Moderate = 'moderate';
}
