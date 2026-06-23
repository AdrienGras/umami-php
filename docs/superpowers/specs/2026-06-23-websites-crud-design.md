# Spec — WebsiteEntrypoint (BOOTSTRAP étape 7.4)

> Domaine **Websites** de la lib `umami-php` : CRUD complet + sous-routes
> `reset` / `transfer` / `daterange` / `values`. Pattern transport-only Saloon v4
> (cf. `docs/SALOON_LIBRARY_DESIGN.md`), cohérent avec Tracking / Auth / Stats déjà livrés.
>
> Date : 2026-06-23. Source vérifié : `reference/umami/` @ v3.1.0.

---

## 1. Objectif & périmètre

Exposer le domaine Websites de l'API Umami v3.1.0 via un `WebsiteEntrypoint` branché
`$umami->websites`. **Périmètre validé : couverture complète** — CRUD (`list/get/create/update/delete`)
+ les 4 sous-routes `reset`, `transfer`, `daterange`, `values`.

Hors périmètre (→ restent BACKLOG) : `active`, `realtime`, `shares`, `export`, `reports`,
`segments`, `event-data/*`, `session-data/*`, `revenue`. (`active` est déjà couvert côté `StatsEntrypoint`.)

## 2. Source vérifié (réfs `reference/umami/`)

| Route | Méthode | Auth | Entrée | Réponse |
|---|---|---|---|---|
| `/api/websites` | GET | Bearer | `page,pageSize,search,includeTeams` (tous opt) | `{data,count,page,pageSize}` |
| `/api/websites` | POST | Bearer | `name`(req ≤100), `domain`(req ≤500), `shareId?`(≤50 nullable), `teamId?`(uuid), `id?`(uuid) | website créé + `shareId` |
| `/api/websites/{id}` | GET | Bearer | — | website |
| `/api/websites/{id}` | POST | Bearer | tous opt : `name`, `domain`, `shareId?`(≤50 nullable), `replayEnabled?`(bool), `replayConfig?`(objet nullable) | website maj + `shareId` |
| `/api/websites/{id}` | DELETE | Bearer | — | `{ok:true}` |
| `/api/websites/{id}/reset` | POST | Bearer | — | `{ok:true}` |
| `/api/websites/{id}/transfer` | POST | Bearer | `userId?`(uuid) **OU** `teamId?`(uuid) — `400` si aucun | website |
| `/api/websites/{id}/daterange` | GET | Bearer | — | `{mindate,maxdate}` ⚠ live |
| `/api/websites/{id}/values` | GET | Bearer | `@dateRange` + `type`(req, fieldsParam) + `search?` | `[{value}]` trié ⚠ live |

`replayConfig` (source `websites/[websiteId]/route.ts:38`) : `{ sampleRate?(0..1), maskLevel?(strict|moderate), maxDuration?(int>0), blockSelector?(string) }`, objet nullable.

`transfer` (source `transfer/route.ts`) : `userId` traité en premier, sinon `teamId`, sinon `badRequest()`.
Côté lib on impose **exactement un** des deux (garde stricte, cohérente avec tracking website/link/pixel).

`values` (source `values/route.ts`) : `type` validé contre `EVENT_COLUMNS ∪ SESSION_COLUMNS ∪ SEGMENT_TYPES`,
sinon `400`. Réponse = liste de `{value}` triée. `type` reste un **string** côté lib (l'ensemble
`fieldsParam` ≠ l'enum `MetricType` existante — ne pas réutiliser `MetricType` ici).

## 3. Surface publique (`$umami->websites`)

| Méthode | Signature | Retour |
|---|---|---|
| `list` | `list(?int $page = null, ?int $pageSize = null, ?string $search = null, ?bool $includeTeams = null)` | `array<string,mixed>` (`{data,count,…}`) |
| `get` | `get(string $id)` | `array<string,mixed>` |
| `create` | `create(string $name, string $domain, ?string $shareId = null, ?string $teamId = null, ?string $id = null)` | `array<string,mixed>` |
| `update` | `update(string $id, ?string $name = null, ?string $domain = null, ?string $shareId = null, ?bool $replayEnabled = null, ?ReplayConfig $replayConfig = null)` | `array<string,mixed>` |
| `delete` | `delete(string $id)` | `void` |
| `reset` | `reset(string $id)` | `void` |
| `transfer` | `transfer(string $id, ?string $userId = null, ?string $teamId = null)` | `array<string,mixed>` |
| `dateRange` | `dateRange(string $id)` | `array<string,mixed>` |
| `values` | `values(string $id, string $type, Period $period, ?string $search = null)` | `list<array<string,mixed>>` |

### Gardes (dans l'Entrypoint, avant tout I/O — `\InvalidArgumentException`)
- `id` (toutes méthodes), `name`/`domain` (`create`), `type` (`values`) : `trim()` + non-vide.
- `create` : `name` ≤ 100, `domain` ≤ 500 (longueurs du schéma zod ; calibrées sur le comportement réel, cf. règle d'or 6).
- `transfer` : **exactement un** de `userId`/`teamId` non-vide (sinon `InvalidArgumentException`).
- Optionnels nuls **omis** du body/query.
- `update` : au moins un champ fourni ? → **non** imposé (un update vide est inoffensif ; on ne durcit pas au-delà du serveur, règle d'or 6).

### Décisions de design (cohérence avec l'implem existante)
- **Entrées** : named args pour les scalaires (comme `AuthEntrypoint::login`, `StatsEntrypoint::metrics`).
  Bloc imbriqué contraint `replayConfig` → **value object readonly `ReplayConfig`** + enum backed
  `MaskLevel` (comme `Period`/`Filters`/`MetricType`). `values` réutilise `Period` (déjà livré).
- **Sorties** : arrays décodés via `asObject()` / `asList()` de `AbstractEntrypoint` (comme Stats/Auth).
  Pas de DTO de sortie typé (déféré, cf. BACKLOG). `delete`/`reset` → `void` (comme `logout`).

## 4. Nouveaux fichiers

```
src/Website/ReplayConfig.php             ← VO readonly : sampleRate?, maskLevel?(MaskLevel), maxDuration?, blockSelector? ; toArray() omet les nuls
src/Enums/MaskLevel.php                  ← enum backed string : Strict='strict', Moderate='moderate'
src/Requests/Website/
  ListWebsites.php        ← GET  /api/websites              ($queryParams)
  GetWebsite.php          ← GET  /api/websites/{id}
  CreateWebsite.php       ← POST /api/websites              (HasBody, $payload)
  UpdateWebsite.php       ← POST /api/websites/{id}         (HasBody, $payload)
  DeleteWebsite.php       ← DELETE /api/websites/{id}
  ResetWebsite.php        ← POST /api/websites/{id}/reset
  TransferWebsite.php     ← POST /api/websites/{id}/transfer (HasBody, $payload)
  GetWebsiteDateRange.php ← GET  /api/websites/{id}/daterange
  GetWebsiteValues.php    ← GET  /api/websites/{id}/values  ($queryParams)
src/Entrypoints/WebsiteEntrypoint.php    ← façade ; branchée public readonly $websites sur UmamiApi
```

Branchement : ajouter `public readonly WebsiteEntrypoint $websites;` au Connector `UmamiApi`
(`$this->websites = new WebsiteEntrypoint($this);`). Requests **non**-`SkipsAuth` → Bearer injecté.

### Règles Saloon (rappel des pièges)
- Propriétés Request : body → `$payload`, query → `$queryParams`. **Jamais** `$body`/`$query`/`$headers`/`$config` (fatal, cf. QUIRKS).
- Pas de base abstraite partagée (formes hétérogènes : GET liste / POST body / DELETE / GET sous-route). Chaque Request est autonome (comme Auth), endpoint dynamique interpolé dans `resolveEndpoint()`.

## 5. Tests (TDD strict — RED vu avant chaque GREEN)

### Unit (`tests/Unit/Website/`)
- **Golden body/query** par méthode : capturer le `PendingRequest` via `MockClient([fn($r)=>MockResponse::make(...)])` puis `$r->body()->all()` / `$r->query()->all()`.
  - `create` : body exact, optionnels nuls omis.
  - `update` : body exact avec `ReplayConfig` sérialisé, et avec `replayConfig` absent.
  - `list`/`values` : query exacte (`Period::toQuery()` mergée, nuls omis).
  - `transfer` : body `{userId}` ou `{teamId}`.
- **Gardes** : `id`/`name`/`domain`/`type` vides → `InvalidArgumentException` ; `transfer` zéro ou deux cibles → exception ; `name`>100 / `domain`>500 → exception.
- **`ReplayConfig::toArray()`** : omet les nuls ; `MaskLevel` mappé sur sa valeur string.

### Intégration (`tests/Integration/Website/`, docker requis, login via `$umami->auth->login()`)
- **Cycle réel** : `create` → `get` (mêmes name/domain) → `update` (change name) → `list` (présent dans `data`) → `reset` → `delete` ; puis `get` du supprimé → `UmamiApiException` (404). Jamais d'assert sur le seul status.
- `dateRange(id)` : structure de réponse (lève le `⚠ live`).
- `values(id, type, Period)` : un `type` valide (ex. `path`/`browser`, ∈ EVENT_COLUMNS ∪ SESSION_COLUMNS, selon données seedées) → liste (lève le `⚠ live`).
- `transfer` : testé seulement si un `userId`/`teamId` cible existe (sinon skip documenté) — sinon garde unit suffit.

## 6. Definition of done

- Source re-vérifié ; `⚠ à vérifier (live)` levés sur `daterange`/`values`/`transfer`/`reset` confirmés en intégration.
- Une Request par appel ; transformations/gardes dans l'Entrypoint ; optionnels nuls omis.
- Erreurs via `UmamiApiResponse` (déjà en place ; 404 du `get` supprimé → `UmamiApiException`).
- Porte `bash scripts/check.sh` **verte** avant commit (règle d'or 8). Intégration lancée séparément (docker).
- Mémoire à jour : `INDEX.md` (ligne Websites), `HANDOFF.md` (entrée datée), `API_UMAMI.md` (`⚠ live` levés),
  `BACKLOG.md` (sous-routes non couvertes restantes), `CONVENTIONS.md` si pattern nouveau, `CLAUDE.md` si règle permanente.
- phpdoc anglaise sur l'API publique ; commit gitmoji `✨`.
```