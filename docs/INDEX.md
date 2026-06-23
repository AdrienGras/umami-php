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
| _(prochain : Stats/reporting — stats/metrics/events/sessions, BOOTSTRAP étape 7.3)_ | | | | | |

> Les Entrypoints cibles (Tracking, Auth, Stats, Website, …) sont décrits dans
> `superpowers/specs/2026-06-23-contrat-lib.md`. Ordre d'implémentation : BOOTSTRAP étape 7.

## Commandes / scripts utilitaires

| Commande | Date | Cible |
|---|---|---|
| `bash scripts/check.sh` | 2026-06-23 | Porte de validation pré-commit (règle d'or 8) : composer validate/audit + cs-fixer + phpstan + phpunit unit |
| `bash scripts/clone-references.sh` | 2026-06-23 | Clone gitignoré du source Umami `@v3.1.0` dans `reference/` (base de la discovery) |
| `bash scripts/seed-umami.sh` | 2026-06-23 | Seed idempotent de l'instance docker de test : attend le login, crée/réutilise le website `umami-php-test`, (ré)écrit `.env.test` (BOOTSTRAP étape 4) |
