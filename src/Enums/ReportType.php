<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Enums;

/**
 * Saved-report / generation type.
 *
 * Mirrors `reportTypeParam` of Umami v3.1.0 (`src/lib/schema.ts`).
 */
enum ReportType: string
{
    case Attribution = 'attribution';
    case Breakdown = 'breakdown';
    case Funnel = 'funnel';
    case Goal = 'goal';
    case Journey = 'journey';
    case Performance = 'performance';
    case Retention = 'retention';
    case Revenue = 'revenue';
    case Utm = 'utm';
}
