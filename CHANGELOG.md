# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-06-24

First stable release. The public API is now considered stable and follows semantic versioning —
no breaking changes without a major bump.

### Added

- **Realtime**: `$umami->stats->realtime($websiteId)` — the realtime activity window
  (`/api/realtime/{id}`).
- **Event-data** (`$umami->eventData`): explore custom-event properties —
  `list`/`get`/`events`/`fields`/`properties`/`values`/`stats`. Reuses the `Period` (epoch
  milliseconds) and `Filters` value objects.

### Notes

- The library now covers the full Umami v3.1.0 API surface that this package targets
  (tracking, auth, stats, websites, users, teams, reports, realtime, event-data).
- `eventData->events()` requires the `event` argument (the live endpoint returns `500` without it).

## [0.2.0] - 2026-06-24

### Added

- **Reports** (`$umami->reports`): saved-report CRUD (`list`/`get`/`create`/`update`/`delete`) plus the
  nine generation endpoints (`funnel`/`retention`/`utm`/`goal`/`journey`/`revenue`/`attribution`/
  `performance`/`breakdown`), with the `ReportType` enum. Reuses the existing `Filters`/`Period` value
  objects. Generation responses are returned in their native shape (list or object).

## [0.1.0] - 2026-06-24

Initial release: a standalone, framework-free PHP client for the Umami Analytics API
(v3.1.0), built on Saloon v4 following a transport-only design.

### Added

- **Tracking** (`$umami->tracking`): `send` / `batch` plus `pageview` / `event` / `identify`.
- **Auth** (`$umami->auth`): `login` (stores the Bearer on the connector) / `logout` / `verify`.
- **Stats** (`$umami->stats`): `stats` / `metrics` / `pageviews` / `events` / `sessions` / `active`,
  with `Period` and `Filters` value objects and the `MetricType` enum.
- **Websites** (`$umami->websites`): CRUD plus `reset` / `transfer` / `dateRange` / `values`.
- **Users** (`$umami->users`): CRUD plus the admin listing and `teams` / `websites` sub-routes.
- **Teams** (`$umami->teams`): CRUD plus `listAll` (admin), `join`, full membership management and
  the team `websites` sub-route.
- `BotFilteredException` re-qualifies Umami's `200 {"beep":"boop"}` bot-filter response as an error.

[0.1.0]: https://github.com/AdrienGras/umami-php/releases/tag/v0.1.0
