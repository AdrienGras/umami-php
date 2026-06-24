# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
