# Registre des features livrées

Catalogue chronologique de ce qui a été construit. Pour chaque entrée : spec (le quoi/pourquoi), plan (le comment), statut. Une ligne par feature.

Voir aussi : `ENVIRONMENT.md` · `QUIRKS.md` · `BACKLOG.md` · `HANDOFF.md` · `superpowers/specs/` · `superpowers/plans/`

---

## Features

| Feature | Date | Spec | Plan | Statut | Notes |
|---|---|---|---|---|---|
| **Discovery API Umami v3.1.0** | 2026-06-23 | `superpowers/specs/2026-06-23-contrat-lib.md` | — | ✅ livré | `docs/API_UMAMI.md` : 95 handlers cartographiés, 8 publics, 3 points sensibles, checklist 3.3 cochée. Reste `⚠ à vérifier (live)` (étape 4 docker). |
| **Socle transport-only** (BOOTSTRAP étape 5) | 2026-06-23 | `superpowers/specs/2026-06-23-contrat-lib.md` | — | ✅ livré | `UmamiApi` (Connector : AlwaysThrowOnErrors+AcceptsJson, `$response` custom, Bearer hors `SkipsAuth`, UA), `UmamiApiResponse` (requalif `beep/boop`→`BotFilteredException`, mapping v4 `failed()`+`createException()`), `UmamiApiException`+`BotFilteredException`, `AbstractEntrypoint`, interface `SkipsAuth`. **5 tests unit verts** (mapping erreur + régime auth). |
| **Tracking** (BOOTSTRAP étape 7.1) | 2026-06-23 | `superpowers/specs/2026-06-23-contrat-lib.md` | — | ✅ livré | `TrackingEntrypoint` (`$umami->tracking`) : `send(Payload, type)`, `batch(Payload[], type)`, raccourcis `pageview/event/identify`. Value object `Payload` (toArray omet nuls), enum `CollectionType`, Requests `SendHit`/`SendBatch` (`SkipsAuth`). Gardes : exactement un de website/link/pixel, name/distinctId non vides. **17 tests unit + 3 intégration** (hit réel, isbot→BotFiltered ET absent, identify). |
| **Auth** (BOOTSTRAP étape 7.2) | 2026-06-23 | `superpowers/specs/2026-06-23-contrat-lib.md` | — | ✅ livré | `AuthEntrypoint` (`$umami->auth`) : `login()` (configure le token du Connector + retourne `LoginResult{token,user}`), `logout()` (efface le token), `verify()` (retourne le user). Requests `Login` (public `SkipsAuth`), `Logout`/`Verify` (Bearer). Connector : token **mutable** + `withToken(?string)` (middleware toujours actif). Gardes username/password. **28 tests unit + 6 intégration** (login réel, verify, 401→UmamiApiException). |
| **Stats/reporting** (BOOTSTRAP étape 7.3) | 2026-06-23 | `superpowers/specs/2026-06-23-contrat-lib.md` | — | ✅ livré | `StatsEntrypoint` (`$umami->stats`) : `stats`/`metrics`/`pageviews`/`events`/`sessions`/`active`. Value objects `Period` (epoch ms `between` / `betweenDates`) + `Filters` (filterParams), enum `MetricType`. Requests GET (base `AbstractStatRequest`). `asObject`/`asList` factorisés dans `AbstractEntrypoint`. **39 tests unit + 9 intégration** (stats réels, metrics path dogfood le harnais, active). |
| **Websites** (BOOTSTRAP étape 7.4) | 2026-06-23 | `superpowers/specs/2026-06-23-websites-crud-design.md` | `superpowers/plans/2026-06-23-websites-crud.md` | ✅ livré | `WebsiteEntrypoint` (`$umami->websites`) : CRUD (`list/get/create/update/delete`) + sous-routes (`reset/transfer/dateRange/values`). Value objects `ReplayConfig` (sampleRate/MaskLevel/maxDuration/blockSelector) + enum `MaskLevel`. Gardes `nonEmpty`/longueur/exactly-one (transfer). `compact()` omet nuls. `Period::toQuery()` réutilisé dans `values`. **17 tests unit + 4 intégration** (cycle CRUD live + dateRange{startDate,endDate} + values[{value,count}]). Quirk : GET sur id supprimé → 200+null (TypeError Saloon). |
| **Users** (BOOTSTRAP étape 7.5) | 2026-06-24 | `superpowers/specs/2026-06-23-contrat-lib.md` | — | ✅ livré | `UserEntrypoint` (`$umami->users`) : CRUD (`list/get/create/update/delete`) + sous-routes (`teams/websites`). `list()` → `GET /api/admin/users` (route admin paginée ; `/api/users` n'expose que POST). Enum `UserRole` (`admin/user/view-only`, type-safe → pas de garde runtime). Gardes : `username` non vide ≤255, `password` 8–255 (non trimé), `id` non vide. `compact()` omet nuls. **16 tests unit + 3 intégration** (cycle CRUD live + role echoé lowercase + sous-routes `websites`/`teams` dogfoodées via owner du website seedé). |
| **Teams** (BOOTSTRAP étape 7.6) | 2026-06-24 | `superpowers/specs/2026-06-23-contrat-lib.md` | — | ✅ livré | `TeamEntrypoint` (`$umami->teams`) : CRUD (`list/get/create/update/delete`) + `listAll` (admin `/api/admin/teams`) + `join` (accessCode) + membres (`members/member/addMember/updateMember/removeMember`) + `websites`. Enum `TeamRole` (`team-member/team-view-only/team-manager` ; `team-owner` non assignable, exclu). Gardes : `name` ≤50, `accessCode` ≤50, `id`/`userId` non vides. 13 Requests (`src/Requests/Team/`). **23 tests unit + 2 intégration** (cycle CRUD+membership complet, join end-to-end via login d'un 2nd user). Quirk : `POST /api/teams` renvoie un tuple `[team, ownerMembership]` → `create()` unwrap `[0]`. |

> Les Entrypoints cibles (Tracking, Auth, Stats, Website, …) sont décrits dans
> `superpowers/specs/2026-06-23-contrat-lib.md`. Ordre d'implémentation : BOOTSTRAP étape 7.

## Commandes / scripts utilitaires

| Commande | Date | Cible |
|---|---|---|
| `bash scripts/check.sh` | 2026-06-23 | Porte de validation pré-commit (règle d'or 8) : composer validate/audit + cs-fixer + phpstan + phpunit unit |
| `bash scripts/clone-references.sh` | 2026-06-23 | Clone gitignoré du source Umami `@v3.1.0` dans `reference/` (base de la discovery) |
| `bash scripts/seed-umami.sh` | 2026-06-23 | Seed idempotent de l'instance docker de test : attend le login, crée/réutilise le website `umami-php-test`, (ré)écrit `.env.test` (BOOTSTRAP étape 4) |
| `.github/workflows/ci.yml` | 2026-06-24 | CI release 0.1.0 : job `gate` (matrice PHP 8.2→8.5, rejoue `scripts/check.sh`) + job `integration` (docker compose Umami + seed + phpunit integration). Déclenché sur push `main` + PR. |
