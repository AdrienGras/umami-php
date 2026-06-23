# Spec — Contrat de la lib umami-php (transport-only, Saloon v4)

Date : 2026-06-23 · Statut : validé en cadrage · Implémentation : après discovery (BOOTSTRAP étape 3)

## Contexte

Client PHP de l'**API complète** d'Umami v3.1.0, bâti sur Saloon v4 selon le pattern
transport-only maison (`docs/SALOON_LIBRARY_DESIGN.md`). Package **autonome, PHP pur**
(aucune dépendance framework). Pendant serveur du client Dart `dashi` (qui couvre le
tracking côté app) : ici on couvre tracking **et** reporting/admin côté serveur PHP.

## Objectifs / non-objectifs

**Objectifs** : couvrir tracking (`/api/send`, `/api/batch`) + reporting/admin (auth,
websites, stats, metrics, events, sessions, et selon besoin users/teams/reports) ;
mapping d'erreur typé ; requalification du 200 bot en exception ; testable (unit + intégration
docker).

**Non-objectifs (v1)** : bridge Symfony (BACKLOG) ; support de versions Umami < 3.1.0 ;
couche métier (rapprochement d'entités, persistance) — c'est au consommateur de la lib.

## Architecture — les 4 couches (cf. SALOON_LIBRARY_DESIGN §2)

| Couche | Rôle ici |
|---|---|
| **Connector** (`UmamiApi`) | Base URL, `AcceptsJson`, `AlwaysThrowOnErrors`, `$response = UmamiApiResponse`, User-Agent descriptif, gestion du Bearer reporting. Entrypoints en `public readonly`. |
| **Entrypoint** | Façade par domaine. Gardes d'entrée + transformations AVANT I/O. Un par domaine (Tracking, Auth, Website, Stats, …). |
| **Request** | Un appel HTTP, descriptif, optionnels nuls omis. Marqueur d'interface `SkipsAuth` pour le tracking. |
| **Response / Exception** | `UmamiApiResponse::toException()` ; **détection `beep/boop` → `BotFilteredException`**. |

### Politique d'erreur (décision de cadrage)

- `AlwaysThrowOnErrors` **partout** : toute réponse `failed()` (non-2xx) lève `UmamiApiException`.
- **Plus** : le 200 du filtre bot (`{"beep":"boop"}`) est requalifié en **`BotFilteredException`**
  (sous-type de `UmamiApiException`) par la `Response` custom — seul cas où un 2xx devient une
  erreur. ⚠ Signature exacte du body à confirmer au source (BOOTSTRAP étape 3.2).
- Conséquence (pattern §8 piège n°1) : un appelant voulant un booléen tolérant (ex. `heartbeat`)
  doit `try/catch`. À documenter dans le README.

### Auth — deux régimes (cf. CLAUDE.md règle 5)

- **Tracking** : `skipAuth`, aucun Bearer. Requests marquées `SkipsAuth` → exclues de
  l'injection du token (même mécanique que la sélection de realm OAuth, SALOON_LIBRARY_DESIGN §5).
  User-Agent descriptif obligatoire (sinon flag bot) — librement définissable en PHP serveur.
- **Reporting/admin** : Bearer obtenu via `AuthEntrypoint::login()`, réinjecté par le Connector
  sur toutes les Requests non-`SkipsAuth`. ⚠ Header/durée/refresh exacts à vérifier au source.

## Domaines / Entrypoints cibles

⚠ Liste à confirmer par la discovery (`find … route.ts`). Cartographie prévisionnelle :

- `TrackingEntrypoint` — `send()` (pageview/event/identify), `batch()`. **Vérifié** (réutilise
  le précis dashi si fourni).
- `AuthEntrypoint` — `login()`, `logout()`, `verify()`. ⚠ routes hors login à vérifier.
- `WebsiteEntrypoint` — CRUD `/api/websites`. ⚠ à vérifier.
- `StatsEntrypoint` — `stats()`, `metrics()`, `events()`, `sessions()`, `pageviews()`,
  `active()`. Partiellement vérifié (stats/metrics côté dashi).
- `UserEntrypoint` / `TeamEntrypoint` / `ReportEntrypoint` — ⚠ à vérifier ; candidats BACKLOG
  si hors usage immédiat.

## Enums & DTO

- **Enums backed** miroir du contrat (ex. `MetricType` : `path`/`entry`/`exit`/`referrer`/…
  — `type=url` de v2 n'existe plus). Valeurs confirmées contre le **contrat live** (casse exacte).
- **DTO de sortie** optionnels via `createDtoFromResponse()` + factory pure dans `Utils/`
  (lève l'exception domaine sur drift de schéma). Décision DTO-vs-array brute par domaine,
  à l'implémentation.

## Stratégie de tests

**Unit (`--testsuite unit`)** : factories `Utils/` (mapping réponse → DTO, drift → exception) ;
construction de body/query des Requests (golden : JSON attendu pour send/batch/identify) ;
mapping d'erreur, **dont le cas `beep/boop` → `BotFilteredException`** sur une réponse 200 mockée.

**Intégration (`--testsuite integration`, requiert docker + seed)** :
- Tracking réel → poll de l'API stats jusqu'à apparition (timeout) — **jamais d'assert sur le
  seul status code** (règle d'or n°3/§6).
- **Test négatif isbot** : UA de bot → 200 reçu, `BotFilteredException` levée, ET absence
  vérifiée dans les stats.
- `identify` : pageviews anonymes → identify → event → vérification du rattachement (champ `id`).
- Auth : `login()` → token réutilisé sur un appel reporting réel.

## Critères d'acceptation v1.0.0

- Tracking + Auth + Stats couverts et testés (websites/users/teams/reports selon arbitrage).
- Tous les marqueurs `⚠ à vérifier` levés sur les domaines livrés.
- `BotFilteredException` testée (unit + intégration).
- phpstan vert, php-cs-fixer appliqué, phpdoc publique complète.
- README : quickstart tracking, auth+reporting, note `try/catch` sur les probes booléens,
  note User-Agent.
- Aucun couplage métier (la lib ne connaît aucune entité, ne persiste rien).
