# BOOTSTRAP — initialisation du projet umami-php (session 1 uniquement)

Document à dérouler **dans l'ordre** par la première session Claude Code. La particularité
de ce projet : **la discovery du source Umami (étape 3) précède toute écriture de code**.
On ne scaffolde pas la lib avant d'avoir la carte vérifiée de l'API. Une fois la checklist
finale validée, ce fichier ne sert plus que de trace — l'état vivant est dans `CLAUDE.md`
et `docs/`.

Prérequis hôte : PHP ≥ 8.2 + Composer, Docker + compose, git, jq, curl.

---

## Étape 1 — Squelette Composer

`composer.json` (déjà fourni dans le pack — vérifier nom/namespace, cf. règle d'identité
du CLAUDE.md, marqueurs `⚠ à confirmer`) :

```
adriengras/umami-php
├── src/                  (PSR-4 AdrienGras\Umami\)
└── tests/                (PSR-4 AdrienGras\Umami\Tests\)
```

```bash
composer install
```

Configurer deux testsuites dans `phpunit.xml` : `unit` (`tests/Unit`) et `integration`
(`tests/Integration`). phpstan (niveau max raisonnable) + php-cs-fixer.

> ⚠ Avant tout : confirmer avec Adrien le **nom Composer** (`adriengras/umami-php` ?) et le
> **namespace** (`AdrienGras\Umami\` ?) — déduits de l'URL GitHub, non confirmés. Retirer
> les marqueurs `⚠ à confirmer` du CLAUDE.md une fois tranché.

## Étape 2 — Clone de référence (gitignoré)

`scripts/clone-references.sh` :

```bash
#!/bin/bash
set -euo pipefail
mkdir -p reference

# ⚠ Vérifier le tag exact : `git ls-remote --tags https://github.com/umami-software/umami | grep 3.1.0`
# Si le nom diffère de v3.1.0, prendre le tag réel et le noter dans docs/ENVIRONMENT.md.
[ -d reference/umami ] || git clone --depth 1 --branch v3.1.0 \
  https://github.com/umami-software/umami reference/umami

# Optionnel : source Saloon en arbitre local (sinon Context7 en première intention).
# [ -d reference/saloon ] || git clone --depth 1 https://github.com/saloonphp/saloon reference/saloon
```

**MCP Context7** : Saloon est documenté sur Context7. Le `.mcp.json` fourni dans le pack
configure le serveur au niveau projet (à commiter). Usage : `resolve-library-id` ("saloon php")
puis `query-docs` pour toute question d'API Saloon. Le clone local (si présent) arbitre les
détails de niveau source.

`.gitignore` :

```
/vendor/
reference/
.env.test
.env.local
.phpunit.result.cache
.php-cs-fixer.cache
.claude/settings.local.json
```

## Étape 3 — DISCOVERY DU SOURCE (le cœur de ce bootstrap) ⚠ AVANT TOUT CODE

Objectif : produire `docs/API_UMAMI.md`, **inventaire complet et vérifié** de l'API Umami
v3.1.0, à partir de `reference/umami/`. C'est la fondation de toute la lib. **Aucune `Request`
n'est écrite avant que le domaine concerné figure, vérifié, dans ce document.**

### 3.1 — Cartographie large (tous les endpoints)

Lister exhaustivement les route handlers :

```bash
find reference/umami/src/app/api -name 'route.ts' | sort
```

Pour **chaque** handler, extraire et consigner dans `docs/API_UMAMI.md`, organisé par domaine :

- **Route** (chemin URL réel, segments dynamiques inclus, ex. `/api/websites/{id}/stats`).
- **Méthode(s)** HTTP exportée(s) (`GET`/`POST`/`PUT`/`DELETE`).
- **Auth** : authentifiée (Bearer) ou `skipAuth` ? (chercher le pattern d'auth en tête de handler).
- **Schéma d'entrée** : le `schema` zod (query et/ou body) — champs, types, contraintes
  (longueurs, enums), optionnels.
- **Forme de réponse** : structure JSON retournée (ou binaire/autre).
- **Réf** `fichier:ligne` (chemin relatif à `reference/umami/`).
- Marquer `⚠ à vérifier (live)` tout point qui devra être reconfirmé contre l'instance docker
  (notamment casse d'enums et `required` réellement appliqués).

Domaines attendus (à confirmer par le `find`, ne pas présumer la liste) :
`auth`, `send` (tracking), `batch` (tracking), `websites`, `websites/{id}/stats|metrics|events|sessions|pageviews|active`,
`users`, `teams`, `reports`, `me`, `heartbeat`. **La liste réelle prime sur cette énumération.**

### 3.2 — Approfondissement des points sensibles (lecture ciblée)

Trois points méritent une lecture fine du source **dès la discovery**, car ils conditionnent
l'architecture de la lib :

1. **Filtre bot / `beep/boop`** (`src/app/api/send/route.ts`) : signature exacte du body de
   rejet, condition du check `isbot`, statut HTTP retourné. → pilote `BotFilteredException`.
2. **`identify`** et **cache token** (`send/route.ts` + `src/tracker/index.js`) : champ `id`,
   header `x-umami-cache`, recalcul de session. → pilote le contrat tracking.
3. **Auth reporting** (`src/app/api/auth/login/route.ts` + middleware d'auth) : forme du token,
   header attendu sur les requêtes authentifiées, durée/refresh éventuel. → pilote le Connector.

> Un `docs/API_UMAMI.md` partiel issu d'un autre projet (dashi) existe peut-être chez Adrien :
> il couvre `/api/send`, `/api/batch` et l'API stats avec réfs `fichier:ligne`. **S'il est
> fourni, le réutiliser comme point de départ** (le transposer du contexte JS/Dart au contexte
> PHP), mais re-vérifier chaque point contre le tag v3.1.0 du clone.

### 3.3 — Sortie de l'étape 3

- `docs/API_UMAMI.md` complété : tous les domaines cartographiés (3.1) + les 3 points sensibles
  approfondis (3.2).
- `docs/QUIRKS.md` alimenté des pièges découverts (200 silencieux, écarts schéma/runtime…).
- **Checklist de fin de discovery** (cocher avant de passer à l'étape 4) :
  - [ ] `find … route.ts` épuisé, chaque handler a une entrée dans `API_UMAMI.md`.
  - [ ] Régime d'auth identifié pour chaque domaine (skipAuth vs Bearer).
  - [ ] Cas `beep/boop` documenté avec réf `fichier:ligne`.
  - [ ] Mécanique `identify` + cache token documentée.
  - [ ] Mécanique d'auth reporting documentée.
  - [ ] Points à reconfirmer en live marqués `⚠ à vérifier (live)`.

## Étape 4 — Instance Umami de test (docker)

`docker-compose.test.yml` : Postgres 16 + image Umami **postgresql-v3.1.0** (⚠ vérifier le
tag exact de l'image GHCR), `APP_SECRET` de test, **PAS de `DISABLE_BOT_CHECK`** (le filtre
actif fait partie du dispositif, règle d'or n°7). Exposer sur un port dédié (ex. 3015).

`scripts/seed-umami.sh` : attendre `/api/heartbeat`, login (`admin`/`umami` — ⚠ confirmer le
provisioning initial v3 au source), créer le website `umami-php-test`, écrire `.env.test`
(`UMAMI_TEST_BASE`, `UMAMI_TEST_WEBSITE_ID`, `UMAMI_TEST_USERNAME`, `UMAMI_TEST_PASSWORD`).
Seed idempotent (GET avant POST, ou `down -v` pour repartir propre) — choisir et documenter
dans `docs/ENVIRONMENT.md`.

Validation manuelle du dispositif anti-200-silencieux :

```bash
# UA plausible → doit apparaître dans les stats
# UA "curl/x.x" (ou un bot connu) → 200 {"beep":"boop"}, ABSENT des stats
```

## Étape 5 — Scaffold de la lib (selon SALOON_LIBRARY_DESIGN.md §3)

Seulement maintenant. Créer la structure transport-only :

```
src/
  UmamiApi.php                         ← Connector (AlwaysThrowOnErrors + AcceptsJson, $response custom)
  Entrypoints/
    Impl/AbstractEntrypoint.php
    TrackingEntrypoint.php             ← /api/send, /api/batch
    AuthEntrypoint.php                 ← login/logout/verify
    WebsiteEntrypoint.php              ← CRUD websites
    StatsEntrypoint.php                ← stats/metrics/events/sessions…
    (… un Entrypoint par domaine confirmé en étape 3)
  Requests/<Domain>/<Action>.php
  Responses/UmamiApiResponse.php       ← toException() + détection beep/boop → BotFilteredException
  Exceptions/UmamiApiException.php
  Exceptions/BotFilteredException.php  ← sous-type, levé sur le 200 bot
  Enums/*.php                          ← miroir des enums du contrat (metrics type, etc.)
  Utils/*.php                          ← factories pures (mapping réponse → DTO), testables
  DTO/<Domain>/Input|Output/*.php      ← optionnel, si DTO typés
```

Auth : le Connector gère le Bearer pour le reporting ; les Requests de tracking (`skipAuth`)
sont **exclues** de l'injection du token (marqueur d'interface, ex. `SkipsAuth`, cf.
SALOON_LIBRARY_DESIGN §5 OAuth multi-realm — même mécanique de sélection par interface).

## Étape 6 — Mémoire projet

Créer `docs/` avec les 6 fichiers (`HANDOFF.md`, `INDEX.md`, `ENVIRONMENT.md`, `QUIRKS.md`,
`BACKLOG.md`, `CONVENTIONS.md`) + `docs/superpowers/specs|plans/`.
- `docs/SALOON_LIBRARY_DESIGN.md` et `docs/superpowers/specs/<date>-contrat-lib.md` sont
  **déjà fournis** — les conserver tels quels.
- Bloc « Mémoire projet » **déjà intégré** au `CLAUDE.md` — ne pas dupliquer.
- `.claude/settings.json` + hook SessionStart qui injecte HANDOFF + INDEX en début de session.
- Premier remplissage : `ENVIRONMENT.md` (port docker, `.env.test`, tag image), `HANDOFF.md`
  (entrée « bootstrap + discovery »), `INDEX.md` (domaines cartographiés).

## Étape 7 — Ordre d'implémentation des Entrypoints

Tranches verticales, chacune : domaine vérifié (étape 3) → Requests → mapping réponse →
tests unit + intégration → INDEX/HANDOFF à jour. Ordre conseillé (du plus ancré au moins) :

1. **Tracking** (`/api/send`, `/api/batch`) — le mieux documenté, porte le cas `beep/boop`.
2. **Auth** (login) — débloque tout le reporting.
3. **Stats/reporting** (stats, metrics, events, sessions) — le gros de la valeur lecture.
4. **Websites CRUD**.
5. **Users / Teams / Reports** — selon besoin réel (peut partir en BACKLOG si hors usage).

## Checklist de validation du bootstrap

- [ ] `composer install` OK ; nom/namespace confirmés, marqueurs `⚠ à confirmer` retirés.
- [ ] `reference/umami` présent (tag 3.1.0 noté dans ENVIRONMENT.md), gitignoré.
- [ ] **`docs/API_UMAMI.md` produit** — checklist de fin de discovery (3.3) entièrement cochée.
- [ ] Instance docker up + seed → `.env.test` écrit, website visible.
- [ ] Test manuel : UA plausible présent dans les stats ; UA bot → `beep/boop` ET absent.
- [ ] `docs/` complet, hook SessionStart fonctionnel.
- [ ] `HANDOFF.md` à jour (entrée bootstrap + discovery).
- [ ] **Aucune `Request` écrite sur un domaine non encore présent dans `API_UMAMI.md`.**
