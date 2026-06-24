# Spec — `ReportEntrypoint` (domaine Reports, étape 7.7)

Date : 2026-06-24 · Statut : validé (design approuvé en session) · Cible : release 0.2.0

## Contexte & objectif

Dernier gros domaine de l'API Umami v3.1.0 non couvert par la lib. Expose `…/api/reports/*`
via `$umami->reports`, en transport-only (cf. `SALOON_LIBRARY_DESIGN.md`).

Le domaine a **deux natures distinctes** :

1. **CRUD des rapports sauvegardés** (`/api/reports`, `/api/reports/{reportId}`) — pattern CRUD rodé.
2. **Génération ad-hoc par type** (9 endpoints `POST /api/reports/<type>`) — paramètres hétérogènes.

Source vérifié : `reference/umami/src/app/api/reports/**/route.ts` + `src/lib/schema.ts`.

## Décisions de design

- **Génération = 9 méthodes nommées + `array $parameters`** (approche B retenue). Une méthode par
  type (`funnel`, `retention`, …) ; le `type` literal est injecté par la méthode ; `parameters`
  reste un array libre. Découvrable (l'IDE liste les 9 rapports), léger, fidèle au transport-only.
  Écarté : VO typés par rapport (dupliquerait les schémas zod → divergence live, règle d'or n°6) ;
  méthode `generate()` unique (peu découvrable).
- **Réutilisation totale des VO existants** : `Stats\Filters` (= `filterParams`, 25 champs, identique)
  et `Stats\Period` (dates). **Aucun nouveau value object.**
- **Un seul nouvel enum** : `ReportType` (9 cas), pour typer le `type` du CRUD (cohérent
  `UserRole`/`TeamRole`).
- **Gardes minimales** (transport-only) : `websiteId`/`reportId` non vides, `name` ≤200,
  `description` ≤500 si fourni. **`parameters` non validé côté lib** — la validation fine
  (steps 2–8, window>0, currency requis…) appartient au live.
- **`filters` dans le body de génération** : envoyé comme **objet JSON** via
  `(object)($filters?->toQuery() ?? [])`, pour garantir `{}` même vide (le schema serveur attend
  `z.object`). ⚠ à confirmer live : filters vide/omis accepté ?

## API publique

### CRUD des rapports sauvegardés

| Méthode | HTTP | Notes |
|---|---|---|
| `list(string $websiteId, ?ReportType $type = null, ?int $page = null, ?int $pageSize = null, ?string $search = null): array` | `GET /api/reports` | `websiteId` **requis** en query ; réponse paginée `{data,count,page,pageSize}` |
| `get(string $reportId): array` | `GET /api/reports/{id}` | |
| `create(string $websiteId, ReportType $type, string $name, array $parameters, ?string $description = null): array` | `POST /api/reports` | `name` ≤200, `description` ≤500 |
| `update(string $reportId, string $websiteId, ReportType $type, string $name, array $parameters, ?string $description = null): array` | `POST /api/reports/{id}` | mêmes gardes |
| `delete(string $reportId): void` | `DELETE /api/reports/{id}` | |

### Génération (9 méthodes, signature identique)

`<type>(string $websiteId, array $parameters, ?Filters $filters = null): array`
→ `POST /api/reports/<type>`, body `{websiteId, type, parameters, filters}`.

Types : `funnel`, `retention`, `utm`, `goal`, `journey`, `revenue`, `attribution`,
`performance`, `breakdown`.

## `parameters` par type (référence — non validé par la lib)

Tous incluent `startDate` + `endDate` (ISO/coerce date). Spécifiques :

| Type | parameters spécifiques | Réponse (forme) |
|---|---|---|
| `funnel` | `window`(>0), `steps`[2–8] `{type:path\|event, value, filters?}` | objet funnel |
| `retention` | `timezone?` | matrice cohortes |
| `utm` | (dates seules) | `{utm_source, utm_medium, utm_campaign, utm_term, utm_content}` |
| `goal` | `type`, `value` | objet goal |
| `journey` | `steps`[2–7], `startStep?`, `endStep?`, `eventType?` | séquence |
| `revenue` | `currency`(requis), `unit?`, `timezone?`, `compare?(prev\|yoy)` | `{chart, total:{…,comparison}}` |
| `attribution` | `model`(first-click\|last-click), `type`(path\|event), `step`, `currency?` | objet attribution |
| `performance` | `metric?(lcp\|inp\|cls\|fcp\|ttfb)`, `unit?`, `timezone?` | `{chart,summary,pages,pageTitles,devices,browsers}` |
| `breakdown` | `fields`[] (path/referrer/os/browser/…) | agrégation multidim |

## Composants

- `src/Enums/ReportType.php` — enum string, 9 cas.
- `src/Requests/Report/` :
  - `ListReports` (GET, query), `GetReport` (GET /{id}), `CreateReport` (POST body),
    `UpdateReport` (POST /{id} body), `DeleteReport` (DELETE /{id}).
  - **`GenerateReport`** — factorisée : `__construct(string $reportType, array $payload)`,
    `resolveEndpoint() = "/api/reports/{$reportType}"`, POST + `HasJsonBody`.
- `src/Entrypoints/ReportEntrypoint.php` (`readonly`, hérite `AbstractEntrypoint`) + câblage
  `UmamiApi::$reports`. Réutilise `compact`/`nonEmpty`/`boundedString` partagés.

## Tests

- **Unit** (`tests/Unit/Report/`) : CRUD (body/query/gardes/endpoints, `type->value`,
  pagination), + les 9 méthodes de génération (chacune : bon endpoint `/api/reports/<type>`,
  `type` injecté dans le body, `filters` sérialisé en objet, `parameters` passé tel quel).
- **Intégration** (`tests/Integration/Report/`) : cycle CRUD d'un report sauvegardé
  (create funnel-typed → get → update → list → delete, gone from list) + génération live
  (`utm` simple + `funnel` représentatif) sur le website seedé → assertions sur la **forme**
  de la réponse (clés présentes), jamais sur le seul status ni le contenu.

## Hors périmètre (→ BACKLOG)

- `GET /api/websites/{websiteId}/reports` — redondant avec `list($websiteId)`.

## Definition of done

Domaine vérifié au source (✅), `⚠ filters vide` levé au live, gardes minimales, mapping
d'erreur via Response, tests unit + intégration verts, porte verte, phpdoc EN, mémoire à jour
(INDEX/HANDOFF/API_UMAMI/BACKLOG/QUIRKS si besoin), CHANGELOG 0.2.0.
