<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Enums;

/**
 * Allowed `type` values for `GET …/metrics` (and `…/metrics/expanded`).
 *
 * EVENT_COLUMNS + SESSION_COLUMNS + the special `channel` (lib/constants.ts).
 * Umami routes by value and returns 400 for anything else. Note: v2's `url` is
 * gone in v3.
 */
enum MetricType: string
{
    // EVENT_COLUMNS
    case Path = 'path';
    case Entry = 'entry';
    case Exit = 'exit';
    case Referrer = 'referrer';
    case Domain = 'domain';
    case Title = 'title';
    case Query = 'query';
    case Event = 'event';
    case Tag = 'tag';
    case Hostname = 'hostname';
    case UtmSource = 'utmSource';
    case UtmMedium = 'utmMedium';
    case UtmCampaign = 'utmCampaign';
    case UtmContent = 'utmContent';
    case UtmTerm = 'utmTerm';

    // SESSION_COLUMNS
    case Browser = 'browser';
    case Os = 'os';
    case Device = 'device';
    case Screen = 'screen';
    case Language = 'language';
    case Country = 'country';
    case City = 'city';
    case Region = 'region';
    case DistinctId = 'distinctId';

    // Special
    case Channel = 'channel';
}
