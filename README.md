# umami-php

A standalone, framework-free PHP client for the [Umami Analytics](https://umami.is) API
(v3.1.0), built on [Saloon v4](https://docs.saloon.dev/). It covers the **full** API surface:
tracking (`/api/send`, `/api/batch`) as well as reporting and admin (auth, stats, websites,
users, teams).

The library is **transport-only**: it knows no business entities, persists nothing and decides
nothing. You instantiate the connector explicitly with resolved values — no framework binding,
no hidden configuration.

## Requirements

- PHP `^8.2`
- An Umami instance (self-hosted or cloud), version `3.1.0`

## Installation

```bash
composer require adriengras/umami-php
```

## Creating the client

```php
use AdrienGras\Umami\UmamiApi;

$umami = new UmamiApi(
    baseUrl: 'https://umami.example.com',
    apiToken: null, // optional: a reporting Bearer token, if you already have one
);
```

The connector exposes one façade per domain: `$umami->tracking`, `$umami->auth`,
`$umami->stats`, `$umami->websites`, `$umami->users`, `$umami->teams`.

## Tracking (no authentication)

Tracking endpoints are unauthenticated server-side. **Always relay the visitor's `userAgent`** —
otherwise Umami's bot filter drops the hit and the library raises a `BotFilteredException`.

```php
$websiteId = '01234567-89ab-cdef-0123-456789abcdef';

// Page view
$umami->tracking->pageview(
    websiteId: $websiteId,
    url: '/pricing',
    title: 'Pricing',
    referrer: 'https://google.com',
    hostname: 'example.com',
    userAgent: $request->getHeader('User-Agent'),
);

// Custom event
$umami->tracking->event(
    websiteId: $websiteId,
    name: 'signup',
    data: ['plan' => 'pro'],
    userAgent: $request->getHeader('User-Agent'),
);

// Attach a stable identity to the session
$umami->tracking->identify(
    websiteId: $websiteId,
    distinctId: 'user-42',
    data: ['email' => 'jane@example.com'],
    userAgent: $request->getHeader('User-Agent'),
);
```

## Reporting & admin (authenticate first)

Call `login()` once: it stores the Bearer token on the connector, so every subsequent
reporting/admin call is authenticated automatically.

```php
$result = $umami->auth->login('admin', 's3cr3t-password');
// $result->token, $result->user

// ... make reporting calls ...

$umami->auth->logout(); // forgets the token client-side
```

### Stats

```php
use AdrienGras\Umami\Stats\Period;
use AdrienGras\Umami\Enums\MetricType;

// epoch milliseconds
$period = Period::between(startAt: 1_700_000_000_000, endAt: 1_700_086_400_000);

$summary  = $umami->stats->stats($websiteId, $period);
$topPaths = $umami->stats->metrics($websiteId, MetricType::Path, $period);
$series   = $umami->stats->pageviews($websiteId, $period);
$active   = $umami->stats->active($websiteId);
```

### Websites

```php
$page    = $umami->websites->list(pageSize: 100);
$website = $umami->websites->get($websiteId);

$created = $umami->websites->create(name: 'My Site', domain: 'example.com');
$umami->websites->update($created['id'], name: 'Renamed');
$umami->websites->delete($created['id']);
```

### Users (admin)

```php
use AdrienGras\Umami\Enums\UserRole;

$users = $umami->users->list(pageSize: 100); // GET /api/admin/users
$user  = $umami->users->create(
    username: 'jane',
    password: 'at-least-8-chars',
    role: UserRole::User,
);
$umami->users->update($user['id'], role: UserRole::ViewOnly);
$umami->users->delete($user['id']);
```

### Teams

```php
use AdrienGras\Umami\Enums\TeamRole;

$team = $umami->teams->create(name: 'Marketing');

// Membership
$umami->teams->addMember($team['id'], $user['id'], TeamRole::Member);
$umami->teams->updateMember($team['id'], $user['id'], TeamRole::Manager);
$umami->teams->removeMember($team['id'], $user['id']);

// Join an existing team with its access code
$umami->teams->join($team['accessCode']);
```

## Error handling

Every failed request raises an exception (Saloon's `AlwaysThrowOnErrors`). All library
exceptions extend `UmamiApiException`.

```php
use AdrienGras\Umami\Exceptions\UmamiApiException;
use AdrienGras\Umami\Exceptions\BotFilteredException;

try {
    $umami->tracking->pageview(websiteId: $websiteId, userAgent: $ua);
} catch (BotFilteredException $e) {
    // Umami returned 200 with `{"beep":"boop"}` — the hit was dropped as a bot.
} catch (UmamiApiException $e) {
    // Any other API error (4xx/5xx).
}
```

`BotFilteredException` is the one case where a `200` response is turned into an error: Umami's
bot filter answers `/api/send` and `/api/batch` with a `200` body of `{"beep":"boop"}`, which
the library re-qualifies as a failure.

## License

MIT © Adrien Gras
