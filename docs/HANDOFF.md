# Handoff — état courant du projet

Notes informelles à destination de la prochaine session (humaine ou Claude). Format libre, chronologique inverse (le plus récent en haut).

**À mettre à jour à la fin d'une session significative**. Pas besoin de noter chaque petit truc — l'idée est de te resituer en 30 secondes en début de session.

---

## 2026-06-24 — Préparation release 0.1.0 (CI + packaging)

### Dernière chose faite
- **CI GitHub Actions** (`.github/workflows/ci.yml`) : job `gate` (matrice PHP **8.2/8.3/8.4/8.5**, rejoue
  `bash scripts/check.sh`) + job `integration` (`docker compose up` Umami 3.1.0 → `seed-umami.sh` → phpunit
  integration → teardown). Déclenché sur push `main` + PR. YAML validé, aucun input non-fiable (pas d'injection).
- **Packaging Packagist** : `composer.json` enrichi (`authors` nom+GitHub **sans email**, `support` issues/source) ;
  `.gitattributes` (export-ignore tests/docs/.github/scripts/configs → dist léger) ; `CHANGELOG.md` (entrée 0.1.0) ;
  badges README (CI, Packagist version, PHP version, License).
- `composer validate --strict` ✅, porte ✅.

### Fait ensuite (même session)
- ✅ **Tag `v0.1.0` + GitHub Release poussés** (commit `92df2e2`). CI **verte observée** sur ce commit :
  Gate 8.2/8.3/8.4/8.5 + Integration (live Umami docker) tous verts.
- ✅ **Bug compat 8.2 attrapé par la CI et corrigé** : `private const string` (typed constant 8.3+) dans 2 tests
  d'intégration → dé-typées. Garde ajoutée (`phpstan.neon phpVersion min 80200`). Cf. QUIRKS.
- Le job CI `integration` boote Umami 3.1.0 + seed en <120s sur le runner — pas de flake observé au 1er run.

### ACTION UTILISATEUR RESTANTE (Adrien)
- **Importer sur Packagist** : https://packagist.org/packages/submit → URL `https://github.com/AdrienGras/umami-php`
  → Submit. Puis activer le hook GitHub→Packagist (auto-update des futurs tags). Tant que non soumis, les badges
  Packagist du README (version/PHP/license) restent « not found » ; le badge CI fonctionne déjà.

### Prochaine chose à creuser
- Pousser le tag `v0.1.0` (après OK), vérifier le 1er run CI vert, soumettre à Packagist.
- Puis **étape 7.7** : `ReportEntrypoint` (dernier domaine) pour une 0.2.0.

### Notes pour future Claude
- La CI `gate` réutilise `scripts/check.sh` (DRY) → toute évolution de la porte se répercute automatiquement.
- `.gitattributes` allège le package : si un nouveau dossier dev-only apparaît, l'y ajouter en `export-ignore`.

---

## 2026-06-24 — Consolidation (factorisation helpers + README + calibrage password)

### Dernière chose faite
- **Factorisation des helpers d'Entrypoint** (refactor sous filet, 99 tests unit toujours verts, porte verte) :
  `compact()`, `nonEmpty()`, nouveau `boundedString()` remontés dans `AbstractEntrypoint`. Website/User/Team
  n'ont plus que des wrappers privés sémantiques qui délèguent. `password` (User) reste local (non trimé, min+max).
  ~70 lignes de duplication supprimées. Documenté dans `CONVENTIONS.md`.
- **README quickstart** écrit (anglais, public Packagist) : install, client, tracking, auth+reporting
  (stats/websites/users/teams), error handling (`UmamiApiException`/`BotFilteredException`). Signatures vérifiées au code.
- **Garde `password min(8)` calibrée live** : POST `/api/users` password <8 → `400` `Too small: expected >=8`.
  Garde confirmée justifiée. ⚠ levé dans `API_UMAMI.md` §4.1.

### Trucs en suspens
- Dette mémoire : aucune nouvelle. Tous les ⚠ des domaines livrés (Tracking/Auth/Stats/Website/User/Team) levés.
- Domaines API restants : `ReportEntrypoint` (`/api/reports/*`) — seul gros domaine reporting non couvert.
- Sous-routes annexes déférées (Website : realtime/shares/export/segments/… ; Team : boards/links/pixels/me-teams).

### Prochaine chose à creuser
- **BOOTSTRAP étape 7.7** : `ReportEntrypoint` (dernier domaine), ou packaging/release (tag v0.1.0, CI GitHub Actions).

### Notes pour future Claude
- Les gardes d'entrée passent TOUTES par `AbstractEntrypoint` maintenant. Pour un nouvel Entrypoint :
  `nonEmpty`/`boundedString`/`compact` sont hérités, n'en recrée pas. Cf. `CONVENTIONS.md`.
- Si une garde ne doit PAS trimmer (secret/password) → la garder locale.

---

## 2026-06-24 — Domaine Teams (BOOTSTRAP étape 7.6)

### Dernière chose faite
- **Teams entièrement livré** (23 tests unit + 2 intégration verts, porte verte) en TDD strict.
  - `src/Entrypoints/TeamEntrypoint.php` (`$umami->teams`) : CRUD (`list/get/create/update/delete`)
    + `listAll` (admin) + `join` + membres (`members/member/addMember/updateMember/removeMember`) + `websites`.
  - Enum `src/Enums/TeamRole.php` (`team-member/team-view-only/team-manager`) — **`team-owner` exclu** (rôle
    implicite du créateur, non assignable via l'API). Type-safe → pas de garde runtime sur `role`.
  - 13 Requests Saloon (`src/Requests/Team/`). Gardes : `name` ≤50, `accessCode` ≤50, `id`/`userId` non vides.
  - Câblé dans `UmamiApi` (`public readonly TeamEntrypoint $teams`).
  - **QUIRK live découvert** : `POST /api/teams` renvoie un **tuple** `[team, ownerMembership]` (transaction
    Prisma qui fuite ses 2 rows), pas l'objet team. `create()` **unwrap** l'élément `[0]`. Consigné QUIRKS.md.
    Les autres routes (`update/addMember/member/updateMember`) renvoient des objets directs (vérifié live).
  - Test intégration `join` **end-to-end** : admin crée team+user, le user se logge sur SON connector et
    `join(accessCode)`, l'admin confirme le membership. Couvre le flux multi-utilisateur réel.
- Mémoire : `INDEX.md`, `API_UMAMI.md` §4.1 (teams ✅ vérifiés), `QUIRKS.md` (tuple create), `BACKLOG.md`
  (sous-routes annexes), ce HANDOFF.

### Trucs en suspens
- Sous-routes Team **non couvertes** (déférées BACKLOG) : `boards`/`links`/`pixels` (ressources annexes),
  `GET /api/me/teams` (alias de `list()`).
- Helpers `compact()`/`nonEmpty`/longueur **toujours dupliqués** (Website/User/Team) → factorisation
  `AbstractEntrypoint` de plus en plus justifiée (BACKLOG). 3 occurrences maintenant.
- Garde `password min(8)` (Users) toujours non calibrée live.
- README quickstart toujours à écrire.

### Prochaine chose à creuser
- **BOOTSTRAP étape 7.7** : `ReportEntrypoint` (`/api/reports/*`) — dernier gros domaine reporting.
  OU README quickstart + factorisation helpers (dette qui s'accumule). À arbitrer avec Adrien.

### Notes pour future Claude
- `TeamRole::Manager->value` === `'team-manager'`. Pas de `team-owner` dans l'enum (volontaire).
- `create()` est le SEUL endroit avec unwrap de tuple. Si tu ajoutes des routes, vérifie la forme live au curl avant.
- Test intégration : noms suffixés `microtime` (pas de tearDown ; collisions évitées par unicité).
- Le flux `join` exige un 2nd connector loggé en tant que le user (le owner est déjà membre → 400 si re-join).

---

## 2026-06-24 — Domaine Users (BOOTSTRAP étape 7.5)

### Dernière chose faite
- **Users entièrement livré** (16 tests unit + 3 intégration verts, porte verte) en TDD strict (RED → GREEN → porte).
  - `src/Entrypoints/UserEntrypoint.php` (`$umami->users`) : `list/get/create/update/delete` (CRUD)
    + `teams/websites` (sous-routes paginées par user).
  - Enum `src/Enums/UserRole.php` (`admin/user/view-only`) — type-safe, calqué sur `MaskLevel` : pas de garde runtime sur `role`.
  - 7 Requests Saloon (`src/Requests/User/`). Gardes : `username` non vide ≤255, `password` 8–255 (non trimé), `id` non vide.
  - Câblé dans `UmamiApi` (`public readonly UserEntrypoint $users`).
  - **Décision clé** : `list()` tape `GET /api/admin/users` (route admin paginée) car `/api/users/route.ts` n'exporte **que** POST. Vérifié au source.
  - **Confirmé live** : `create` echo le `role` en lowercase (`user`/`view-only`/`admin`) ; `websites->get()` expose `userId` (utilisé pour dogfooder la sous-route `users->websites`).
- Mémoire consolidée : `INDEX.md` (ligne Users), `API_UMAMI.md` §4.1 (`⚠` users levés), `BACKLOG.md` (UserEntrypoint coché + factorisation helpers notée), ce HANDOFF.

### Trucs en suspens
- **Garde `password` min(8)** : reproduite du zod source, mais **non calibrée contre le live** (règle d'or n°6) — l'API pourrait être plus laxiste. Marqueur `⚠ à vérifier (live)` laissé dans `API_UMAMI.md`. Tester un create avec password <8 pour trancher.
- Sous-routes Team non couvertes : tout `TeamEntrypoint` (`/api/teams/*`) reste à faire.
- Helpers `compact()`/`nonEmpty`/longueur dupliqués entre `WebsiteEntrypoint` et `UserEntrypoint` → factorisation possible dans `AbstractEntrypoint` (notée BACKLOG).
- README quickstart toujours à écrire.

### Prochaine chose à creuser
- **BOOTSTRAP étape 7.6** : `TeamEntrypoint` (CRUD teams + `join` + sous-routes `users`/`websites`) ou `ReportEntrypoint`, ou README quickstart — selon arbitrage avec Adrien.

### Notes pour future Claude
- Pattern Users = copie conforme de Websites. `UserRole::User->value` === `'user'`.
- `update()` n'envoie que les champs fournis (`compact` + `role?->value`). `password` non trimé (espaces significatifs).
- Test intégration : username suffixé `microtime` pour éviter les collisions « User already exists » entre runs.
- GET-after-delete sur user : non testé (contourné via `list()` comme pour Websites). Comportement potentiellement ≠ du 200+null des websites.

---

## 2026-06-23 — Domaine Websites (BOOTSTRAP étape 7.4)

### Dernière chose faite
- **Websites entièrement livré** (17 tests unit + 4 intégration verts, porte verte).
  - `src/Entrypoints/WebsiteEntrypoint.php` (`$umami->websites`) : `list/get/create/update/delete` (CRUD)
    + `reset/transfer/dateRange/values` (sous-routes). Enum `MaskLevel`, VO `ReplayConfig`.
  - 9 Requests Saloon (`src/Requests/Website/`). Guards `nonEmpty`/longueur/exactly-one.
  - Tests intégration cycle CRUD live (commits : Task 3 `c944f51`, Task 4 `021881a`, Task 5 voir dernier commit).
  - **Formes live confirmées** : `dateRange` → `{startDate, endDate}` (ISO-string, ≠ doc initiale `{mindate,maxdate}`) ;
    `values` → `[{value: string, count: int}]` (doc disait `[{value}]` seulement).
  - **Quirk découvert** : GET sur id supprimé → HTTP 200 + body `null` → `TypeError` Saloon (pas `UmamiApiException`).
    Consigné dans `API_UMAMI.md` §4.2 et `QUIRKS.md`.
- Mémoire consolidée : `INDEX.md` (une ligne Websites), `API_UMAMI.md` §4.2 (`⚠` levés), `BACKLOG.md`,
  `HANDOFF.md` (entrées Task 3/Task 4 fusionnées ici).

### Trucs en suspens
- Sous-routes Website non couvertes : `active` (via Stats), `realtime`, `shares`, `export`,
  `segments`, `event-data/*`, `session-data/*`, `revenue` → BACKLOG.
- README quickstart toujours à écrire.

### Prochaine chose à creuser
- **BOOTSTRAP étape 7.5** : `UserEntrypoint` / `TeamEntrypoint` / `ReportEntrypoint` — ou README quickstart
  selon priorité avec Adrien (cf. `BACKLOG.md`).

### Notes pour future Claude
- `Period::between(int $startAt, int $endAt)` → clés `startAt`/`endAt` en epoch ms via `toQuery()`.
- GET sur id supprimé : HTTP 200 + `null` body → `TypeError` (pas notre exception). Contourner via `list()`.
- `transfer` guard : `$hasUser === $hasTeam` → throw (0 ou 2 → false===false ou true===true).

---

## 2026-06-23 — 🏁 Récap de session (étapes 4 → 7.3)

**Grosse session.** Partie d'un bootstrap discovery-only, on a livré l'infra de test + 3 domaines
métier de la lib, tout en TDD, porte verte à chaque commit.

- **Étape 4** — instance Umami docker de test + `scripts/seed-umami.sh` idempotent + dispositif
  anti-200 validé live (`9391ba0`).
- **Étape 5** — socle transport-only : Connector, `UmamiApiResponse` (requalif `beep/boop`),
  exceptions, `SkipsAuth`, `AbstractEntrypoint` (`013d654`).
- **Étape 7.1** — **Tracking** : `send/batch` + `pageview/event/identify`, `Payload`, `CollectionType` (`4c49692`).
- **Étape 7.2** — **Auth** : `login/logout/verify`, token mutable + `withToken` (`4d7fa20`).
- **Étape 7.3** — **Stats** : `stats/metrics/pageviews/events/sessions/active`, `Period`/`Filters`/`MetricType` (`8f61d37`).

**État** : 6 commits sur `main`, **48 tests** (39 unit + 9 intégration) verts, instance docker up + seedée.
API publique : `$umami->tracking|auth|stats`.

**Reprise → étape 7.4 (Websites CRUD)** ou **README** (cf. `BACKLOG.md`). Le pattern est rodé :
value objects d'entrée + Requests (attention propriétés réservées `$body/$query/...`) + Entrypoint
+ TDD (RED via stub/`MockClient`, GREEN, porte). Auth déjà géré par le Connector après `login()`.

**Note working-tree** : `.vscode/settings.json` apparaît supprimé (non commité, pas de mon fait) —
laissé tel quel, à trancher avec Adrien.

---

## 2026-06-23 — Domaine Stats/reporting (BOOTSTRAP étape 7.3)

### Dernière chose faite
- **Stats livré en TDD** (39 tests unit + 9 intégration verts, porte verte).
  - `src/Entrypoints/StatsEntrypoint.php` (`$umami->stats`) : `stats`, `metrics`, `pageviews`,
    `events`, `sessions`, `active`. Construit la query depuis `Period`+`Filters`+extras.
  - Value objects `src/Stats/Period.php` (`between` epoch **ms** / `betweenDates`, toQuery) et
    `src/Stats/Filters.php` (filterParams complet, toQuery omet nuls). Enum `src/Enums/MetricType.php`.
  - Requests `src/Requests/Stats/*` via base `AbstractStatRequest` (GET, segment par sous-classe).
  - **Refactor** : `asObject()`/`asList()` remontés dans `AbstractEntrypoint` (réutilisés par Auth+Stats).
  - **Dogfood** : `IntegrationTestCase::recordedPaths()` utilise `stats->metrics(type=path)`.
- **Nouveau QUIRK** : `Saloon\Http\Request::$query` (comme `$body`) est réservé → propriété
  `$queryParams`. Consigné QUIRKS + CONVENTIONS.

### Trucs en suspens
- Sous-routes stats non couvertes (volontaire) : `metrics/expanded`, `events/series`, `events/stats`,
  `sessions/stats|weekly|[id]`, `event-data/*`, `session-data/*`. → BACKLOG si besoin.
- Réponses retournées en **array décodé** (pas de DTO de sortie typé pour stats) — décision
  pragmatique (formes variables). DTO possible plus tard.
- README quickstart toujours à écrire (tracking + auth + stats).

### Prochaine chose à creuser
- **BOOTSTRAP étape 7.4 — Websites CRUD** (`GET/POST/DELETE /api/websites` + `/[id]`), ou
  **Users/Teams/Reports** selon arbitrage (candidats BACKLOG, cf. spec contrat). Le Connector
  authentifie déjà (login). Pattern établi : value objects d'entrée + Requests + Entrypoint + TDD.

### Notes pour future Claude
- Stats : `$umami->auth->login(...)` puis `$umami->stats->stats($id, Period::between($startMs,$endMs))`.
  **epoch ms** pour `between`. `Filters` pour les filtres optionnels. `metrics` exige un `MetricType`.
- Inspecter une query construite en test : capturer le `PendingRequest` via un **mock callable**
  (`new MockClient([fn(PendingRequest $r) => MockResponse::make(...)])`) puis `$r->query()->all()`.

---

## 2026-06-23 — Domaine Auth (BOOTSTRAP étape 7.2)

### Dernière chose faite
- **Auth livré en TDD** (28 tests unit + 6 intégration verts, porte verte).
  - `src/Entrypoints/AuthEntrypoint.php` (`$umami->auth`) : `login()` (gardes username/password,
    envoie `Login`, **configure le token du Connector** via `withToken`, retourne `LoginResult`),
    `logout()` (envoie `Logout` + efface le token local), `verify()` (retourne le user array).
  - `src/Auth/LoginResult.php` (readonly `token` + `user`).
  - `src/Requests/Auth/{Login,Logout,Verify}.php`. `Login` = public `SkipsAuth` (pas de Bearer) ;
    `Logout`/`Verify` = Bearer.
  - **Connector token mutable** : `private ?string $bearerToken` (init = `apiToken`), middleware
    **toujours enregistré** (skip si token null OU `SkipsAuth`), méthode publique
    `withToken(?string): static`. AuthRegimeTest préservé.
- **Dogfood** : `IntegrationTestCase::reportingToken()` utilise désormais `auth->login()` (plus la
  Request anonyme). `AuthIntegrationTest` : login réel (token + user admin + isAdmin), verify,
  **401 mauvais mot de passe → `UmamiApiException`** (confirmé live).
- Helper `AuthEntrypoint::asObject(mixed)` : normalise un JSON décodé en `array<string,mixed>`
  (phpstan max n'infère pas les clés string depuis `is_array` — pas de cast/@var de contournement).

### Trucs en suspens
- `logout` côté serveur = no-op sans Redis (token reste valide) ; la lib oublie le token côté client
  (seul effet fiable). Documenté.
- README quickstart (tracking + auth/reporting, note try/catch BotFilteredException, note UA visiteur,
  note logout no-op) → toujours à écrire.

### Prochaine chose à creuser
- **BOOTSTRAP étape 7.3 — Stats/reporting** : `StatsEntrypoint` (`stats`, `metrics`, `pageviews`,
  `events`, `sessions`, `active`). Toutes GET + Bearer (déjà géré par le Connector après login).
  Attention aux **deux contrats de date** (cf. QUIRKS) et `startAt/endAt` en **epoch ms**. Enum
  `MetricType` (EVENT_COLUMNS/SESSION_COLUMNS, cf. API_UMAMI §2). Le harnais d'intégration a déjà
  un `recordedPaths()` (metrics type=path) à transposer en `StatsEntrypoint`.

### Notes pour future Claude
- `$umami->auth->login($u,$p)` configure le token ; les appels reporting suivants sur **le même
  connector** sont authentifiés automatiquement. `$umami->withToken($persisted)` pour réutiliser un token.
- Pattern Entrypoint qui mute le Connector : OK (le Connector porte l'état d'auth de transport, cf.
  TokenStore du pattern §5). L'Entrypoint reste `readonly` (il ne fait que tenir la réf au Connector).

---

## 2026-06-23 — Domaine Tracking (BOOTSTRAP étape 7.1)

### Dernière chose faite
- **Tracking livré en TDD** (17 tests unit + 3 intégration verts, porte verte). API publique
  choisie avec Adrien : **value object `Payload` + raccourcis sémantiques**.
  - `src/Tracking/Payload.php` — readonly, named args, ~22 champs (miroir du schéma `send`),
    `toArray()` omet les nuls.
  - `src/Enums/CollectionType.php` — `event`/`identify`/`performance`.
  - `src/Requests/Tracking/SendHit.php` (`/api/send`) + `SendBatch.php` (`/api/batch`) —
    `implements HasBody, SkipsAuth` ; payload nommé `$payload`/`$hits`. Batch = array JSON racine.
  - `src/Entrypoints/TrackingEntrypoint.php` — `send(Payload,$type)`, `batch(Payload[],$type)`,
    `pageview()`, `event()`, `identify()`. Gardes : exactement un de website/link/pixel ;
    name/distinctId non vides ; batch non vide. Branché sur le Connector (`$umami->tracking`).
  - Tests : `tests/Unit/Tracking/{Payload,TrackingEntrypoint}Test.php`, `tests/Unit/ConnectorTest.php`,
    `tests/Integration/Tracking/TrackingIntegrationTest.php` + `IntegrationTestCase` (charge `.env.test`,
    skip si absent, login + metrics via Requests anonymes ; poll `metrics?type=path`).
- **Découverte majeure (QUIRK)** : le UA par défaut de la lib **est flagué bot** → tout hit sans
  `payload.userAgent` est filtré. `userAgent` du payload **prime** sur le header (`lib/detect.ts:127`).
  En tracking, relayer le UA du **visiteur**. Validé live (test négatif isbot → `BotFilteredException`
  ET absent des stats).

### Trucs en suspens
- `AuthEntrypoint` pas encore là : le harnais d'intégration fait login/metrics via Requests
  anonymes en attendant (à remplacer par `AuthEntrypoint`/`StatsEntrypoint` quand livrés).
- `identify` : test d'intégration = smoke (200 accepté). Rattachement distinctId complet (via API
  sessions) → à approfondir quand le domaine Sessions sera là (noter BACKLOG si besoin).
- README quickstart tracking (note UA visiteur + try/catch BotFilteredException) → à écrire (étape doc).

### Prochaine chose à creuser
- **BOOTSTRAP étape 7.2 — Auth** : `AuthEntrypoint::login()/logout()/verify()`. `login` PUBLIC
  (`{username,password}` → `{token,user}`), les autres Bearer. Une fois le token obtenu, le
  Connector l'injecte déjà (middleware) sur les requêtes non-`SkipsAuth`. Puis **7.3 Stats**.

### Notes pour future Claude
- Lancer l'intégration : instance docker up + `bash scripts/seed-umami.sh`, puis
  `vendor/bin/phpunit --testsuite integration`. Sans `.env.test`, la suite skip proprement.
- Mock body en unit : `$response->getPendingRequest()->body()->all()` est `?BodyRepository`/`mixed`
  → garder le null + `is_array` (phpstan max). `MockResponse::make($body,$status)`.
- DX tracking : `$umami->tracking->pageview($id, url:'/x', userAgent:$visitorUa)`.

---

## 2026-06-23 — Socle transport-only de la lib (BOOTSTRAP étape 5)

### Dernière chose faite
- **Socle livré en TDD** (5 tests unit verts, porte `check.sh` verte phpstan max inclus) :
  - `src/UmamiApi.php` — Connector : `AlwaysThrowOnErrors` + `AcceptsJson`, `$response =
    UmamiApiResponse`, UA descriptif, **Bearer injecté par middleware sauf `SkipsAuth`**,
    `baseUrl` requis (pas de défaut bidon).
  - `src/Responses/UmamiApiResponse.php` — requalif `beep/boop` (200) → `BotFilteredException`
    via override **`failed()` + `createException()`** (mécanique **v4**, ≠ doc pattern v3).
  - `src/Exceptions/UmamiApiException.php` (`getStatusCode()`, `errorCode()`) +
    `BotFilteredException.php` (sous-type). PHP pur, **pas** de `HttpExceptionInterface`.
  - `src/Contracts/SkipsAuth.php` (marqueur), `src/Entrypoints/Impl/AbstractEntrypoint.php`.
  - Tests : `tests/Unit/ErrorMappingTest.php` (bot→BotFiltered, 4xx→UmamiApi+errorCode+status),
    `tests/Unit/AuthRegimeTest.php` (Bearer sur reporting, absent sur `SkipsAuth`, absent sans token).
- **Vérif source Saloon v4** : `throw()` → `shouldThrowRequestException()` → `failed()` (PAS
  `toException()`). D'où l'override `failed()` pour requalifier un 2xx. Consigné QUIRKS + CONVENTIONS.

### Trucs en suspens
- Aucun Entrypoint métier encore (le Connector n'expose pas encore de `public readonly` entrypoint —
  ils s'ajoutent avec leur domaine). Pas de Request réelle encore.
- `src/.gitkeep` / `tests/Unit/.gitkeep` devenus inutiles (dossiers peuplés) — à nettoyer au commit.

### Prochaine chose à creuser
- **BOOTSTRAP étape 7.1 — Tracking** : `TrackingEntrypoint` + Requests `Send`/`Batch`
  (`implements HasBody, SkipsAuth` ; payload nommé `$payload` ; optionnels nuls omis ; `type`
  ∈ event/identify/performance ; payload exige exactement un de website/link/pixel). Brancher
  l'entrypoint en `public readonly` sur le Connector. Tests unit (golden body) + intégration
  (hit réel + poll stats, test négatif isbot → `BotFilteredException` ET absent). Contrat : `API_UMAMI.md` §3.1/§3.2.

### Notes pour future Claude
- TDD strict ici : RED vu avant chaque GREEN. Reproduire pour Tracking (golden JSON du body d'abord).
- Mock Saloon v4 : `MockResponse::make($body,$status)` + `withMockClient` ; inspecter la requête
  résolue via `$response->getPendingRequest()->headers()->all()`. Classe anonyme Request = `new class ()`.
- Le contrat `send` réponse `{cache,sessionId,visitId}` et `beep/boop` sont **déjà validés live** (étape 4).

---

## 2026-06-23 — Instance docker de test + seed + validation anti-200 (BOOTSTRAP étape 4)

### Dernière chose faite
- **Instance Umami de test UP** : `docker compose -f docker-compose.test.yml up -d` (image
  `ghcr.io/umami-software/umami:3.1.0` + Postgres 16, port **3015**). Boot rapide (~quelques s).
- **`scripts/seed-umami.sh` écrit et livré** (idempotent) : attend le **login** (readiness réelle —
  le heartbeat ment, il répond 200 avant les migrations), réutilise/crée le website `umami-php-test`
  (domain `umami-php.test`) via GET-avant-POST, (ré)écrit `.env.test`. Testé 2× : create puis réutilise
  le même UUID. `.env.test` régénéré (l'ancien était un **résidu copié de dashi** : `dashi.test`).
- **Dispositif anti-200-silencieux VALIDÉ live** : hit UA navigateur sur `/human` → `{cache,sessionId,
  visitId}` + apparaît dans `metrics?type=path` (pageviews:1) ; hit UA `curl` sur `/bot` → `{"beep":
  "boop"}` ET **absent** des stats. Comportement attendu (règles d'or 3 & 7).
- **Confirmations live consignées dans `API_UMAMI.md`** (✓ étape 4) : `startAt/endAt` en epoch **ms**,
  réponse `send` `{cache,sessionId,visitId}`, `beep/boop`, pagination `{data,count,page,pageSize}`,
  login `{token,user{username,role,isAdmin}}`. Admin par défaut `admin`/`umami` (source
  `scripts/seed/index.ts:129`).
- **QUIRKS** : ajout du piège **rtk** (corrompt les commandes shell multi-lignes → passer par un `.sh`
  dans le scratchpad) + note epoch ms (vs `timestamp` payload en secondes).

### Trucs en suspens
- Rien de cassé. La plupart des `⚠ à vérifier (live)` des domaines stats/event-data/session-data
  **restent à lever** (formes de réponse non encore appelées en live) — se feront au fil de l'étape 7.
- Pas encore commité : `scripts/seed-umami.sh` + mises à jour mémoire (ENVIRONMENT, INDEX, QUIRKS,
  API_UMAMI, HANDOFF). Porte de validation `scripts/check.sh` à passer avant commit.

### Prochaine chose à creuser
- **BOOTSTRAP étape 5** : scaffold transport-only de la lib (`src/UmamiApi.php` Connector +
  Entrypoints/Requests/Responses/Exceptions), puis **étape 7.1 Tracking** (`/api/send`, `/api/batch`)
  en premier — c'est le domaine qui porte `beep/boop` (déjà validé live, factories testables d'emblée).

### Notes pour future Claude
- L'instance tourne déjà (ou `up -d` + `seed`). Pour repartir propre : `down -v` puis up + seed.
- Website de test courant : voir `.env.test` (`UMAMI_TEST_WEBSITE_ID`). Credentials `admin`/`umami`.
- Test live type : écrire le script dans le scratchpad et `bash` (cf. quirk rtk), jamais inliner un
  gros bloc curl+jq.

---

## 2026-06-23 — Discovery du source Umami (BOOTSTRAP étape 3)

### Dernière chose faite
- **`composer.lock` sorti du suivi** (convention librairie) + `.gitignore` — commit `fc76d51`.
- **Clone `reference/umami@v3.1.0`** (`c78ff36`, gitignoré) via `scripts/clone-references.sh`.
- **`docs/API_UMAMI.md` produit** : cartographie vérifiée des **95 route handlers**, organisée par
  domaine, avec les 3 points sensibles approfondis et la checklist 3.3 entièrement cochée. Méthode :
  extraction mécanique (méthodes + auth) pour les 95, puis 4 sous-agents parallèles pour les schémas
  par domaine + lecture directe des fichiers critiques (`send`, `batch`, `auth/login`, `lib/request`,
  `lib/auth`, `lib/response`).
- **Faits durs établis** :
  - Bot `{"beep":"boop"}` = **HTTP 200** sur `send/route.ts:131` (et `record`). Signature confirmée.
  - Auth reporting = `Authorization: Bearer <token>` ; token = `.token` de `login` ; JWT stateless ;
    pas de refresh ; logout no-op sans Redis.
  - `identify` : `type:'identify'` + champ `id` (distinctId) ; cache via header `x-umami-cache` ;
    réponse send `{cache, sessionId, visitId}`.
  - **8 endpoints publics** (5 `skipAuth` + 3 sans `parseRequest` : `heartbeat`, `scripts/telemetry`,
    `share/[slug]`).
  - Enums metric `type` confirmés (EVENT_COLUMNS/SESSION_COLUMNS/`channel`), rôles, operators.
- **QUIRKS.md** enrichi de 6 pièges (beep/boop send+record, batch compte les bots en `processed`,
  export = ZIP base64 en JSON, publics sans parseRequest, logout no-op, contrats de date incohérents).

### Trucs en suspens
- Tout reste `⚠ à vérifier (live)` (casse enums, `required` réels, formes de réponse non tracées au
  SQL) → ne sera levé qu'à l'**étape 4** (instance docker + seed). `docker-compose.test.yml` est prêt.
- Pas encore commité : discovery (`docs/API_UMAMI.md`, QUIRKS, INDEX, HANDOFF).

### Prochaine chose à creuser
- **BOOTSTRAP étape 4** : `docker compose -f docker-compose.test.yml up -d`, créer `scripts/seed-umami.sh`,
  écrire `.env.test`, valider le dispositif anti-200-silencieux (UA bot → `beep/boop` ET absent des stats).
- Puis **étape 5** : scaffold de la lib, en commençant par le **Tracking** (étape 7, ordre conseillé).

### Notes pour future Claude
- Les sous-agents ont des `agentId` réutilisables (SendMessage) si tu veux approfondir un domaine sans
  re-cloner le contexte. Sinon le source est dans `reference/umami/` (gitignoré).
- `docs/API_UMAMI.md` §2 = référentiels d'enums + params communs (`@dateRange`/`@filters`/`@paging`) ;
  §4.3 = table des endpoints stats avec leur contrat de date exact (deux familles, cf. QUIRKS).

---

## 2026-06-23 — Bootstrap (étapes 1-2) + Saloon v4 + porte de validation + mémoire projet

### Dernière chose faite
- **Scaffold + outillage (BOOTSTRAP étapes 1-2)** : fichiers du pack rangés à leur place
  (`CLAUDE.md`, `BOOTSTRAP.md`, `.mcp.json`, `docs/SALOON_LIBRARY_DESIGN.md`,
  `docs/superpowers/specs/2026-06-23-contrat-lib.md`). Créé `phpunit.xml` (testsuites
  `unit`/`integration`), `phpstan.neon` (level max), `.php-cs-fixer.dist.php`,
  `scripts/clone-references.sh`, `.gitignore`, arbo `src/` + `tests/`.
- **Identité verrouillée** : `adriengras/umami-php` + namespace `AdrienGras\Umami\`
  (marqueurs `⚠ à confirmer` retirés du CLAUDE.md).
- **Saloon v3 → v4** : Saloon v3 est intégralement frappé par 3 CVE (dont une *high* :
  désérialisation `AccessTokenAuthenticator`), toutes corrigées en **v4.0.0** uniquement.
  Décision validée avec Adrien : bump `composer.json` en `^4.0`, `composer audit` désormais
  vert. Répercuté v3→v4 dans toute la spec.
- **Porte de validation (CLAUDE.md règle d'or 8)** : `scripts/check.sh` enchaîne
  `composer validate` + `composer audit` + `php-cs-fixer` + `phpstan` + `phpunit unit`.
  Vert obligatoire avant tout commit.
- **Système de mémoire projet** : ce dossier `docs/` + hook SessionStart (`.claude/`).
- **rtk** initialisé (`rtk init`) : bloc d'instructions ajouté au `CLAUDE.md`, `.rtk/filters.toml`
  (template, à enrichir de filtres PHP plus tard).
- **Convention gitmoji** établie (règle d'or 9) : tout commit commence par un gitmoji.
- **`docker-compose.test.yml` présent** (Postgres 16 + `ghcr.io/umami-software/umami:3.1.0`, port 3015).
- **Premier commit `🎉` poussé sur `main`** : socle bootstrap (scaffold + outillage + mémoire + rtk).

### Trucs en suspens
- `phpstan` est en « skip » dans `check.sh` tant que `src/` est vide (normal, disparaît à l'étape 5).
- `php-cs-fixer` émet un warning « PHP 8.5 vs min 8.2 » (informatif, runtime hôte = 8.5.7).

### Prochaine chose à creuser
- **BOOTSTRAP étape 3 — discovery du source Umami** (le cœur) : `bash scripts/clone-references.sh`
  puis produire `docs/API_UMAMI.md` (cartographie vérifiée de chaque `route.ts`). **Aucune
  Request ne s'écrit avant.** Les 3 points sensibles : filtre bot `beep/boop`, `identify`/cache
  token, auth reporting.

### Notes pour future Claude
- `⚠ à vérifier v4` posé sur `allowBaseUrlOverride` (design doc §7.4) : c'était la faille SSRF
  CVE-2026-33182, durcie en v4 — re-confirmer le mécanisme au source/Context7 avant usage.
- Le bloc « Mémoire projet » de `CLAUDE.md` était déjà fourni par le pack (plus spécifique que
  le template générique) : on l'a gardé tel quel, pas dupliqué.
