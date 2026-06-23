# Quirks & pièges connus

Comportements non-évidents découverts au fil du projet. Un H2 par quirk, avec une date.

**Si tu en découvres un nouveau pendant une session : ajoute-le ici dès la découverte, pas plus tard.**

> Les pièges Saloon génériques (probe booléen + `AlwaysThrowOnErrors`, collision `$body`,
> `resolveBaseUrl()` ignoré, multipart resource) sont dans `SALOON_LIBRARY_DESIGN.md` §8.
> Ici on consigne les pièges **spécifiques à ce projet / cet environnement**.

---

## Saloon v3 entièrement vulnérable — fixé en v4 uniquement (2026-06-23)

**Découvert** : au premier `composer install`.

**Symptôme** : `composer install` refuse `saloonphp/saloon ^3.0` — toutes les versions v3
(v3.0.0 → v3.15.0) bloquées par 3 security advisories.

**Cause** : CVE-2026-33942 (*high*, désérialisation `AccessTokenAuthenticator`), CVE-2026-33183
(path traversal fixtures), CVE-2026-33182 (SSRF via URL absolue surchargeant la base URL).
Toutes `Affected: <4.0.0` — aucun v3 patché.

**Workaround** : passage à `saloonphp/saloon ^4.0`. `composer audit` vert. Spec répercutée v3→v4.

**Référence** : `composer.json`, advisories Packagist `PKSA-xnj5-w74d-6wmz` / `-rnpm-45mg-w6ht` / `-5szq-gvrg-ttfq`.

## `mbstring` requis pour PHPUnit (2026-06-23)

**Découvert** : premier `vendor/bin/phpunit`.

**Symptôme** : « PHPUnit requires the "mbstring" extension, but the "mbstring" extension is not available. »

**Cause** : l'extension `mbstring` n'était pas installée sur l'hôte (PHP 8.5).

**Workaround** : `sudo apt install php8.5-mbstring` puis vérifier `php -m | grep mbstring`.

**Référence** : hôte `/srv/AdrienGras/umami-php`.

## phpstan « No files found » tant que `src/` est vide (2026-06-23)

**Découvert** : mise en place de la porte de validation.

**Symptôme** : `phpstan analyse` retourne un **code non-zéro** avec « No files found to analyse »
avant l'écriture de tout code (étape 5), ce qui ferait un faux rouge sur la porte de commit.

**Cause** : phpstan considère une absence de fichiers comme une erreur.

**Workaround** : `scripts/check.sh` traite spécifiquement ce message exact en « skip » (toute
*vraie* erreur phpstan reste rouge). Le skip disparaît dès qu'il y a du code dans `src/`.

**Référence** : `scripts/check.sh`.

## Le 200 bot `{"beep":"boop"}` existe sur `send` ET `record` (2026-06-23, discovery)

**Découvert** : lecture de `send/route.ts:131` et `record/route.ts`.

**Symptôme** : un User-Agent détecté comme bot (`isbot`) reçoit **HTTP 200** avec body
`{"beep":"boop"}` — Saloon le voit `successful()`, aucune exception. Vaut pour `/api/send` ET
`/api/record` (replays).

**Cause** : `if (!process.env.DISABLE_BOT_CHECK && isbot(userAgent)) return json({ beep: 'boop' });`.

**Workaround** : la `Response` custom inspecte le body et requalifie en `BotFilteredException`
(CLAUDE.md règle 3). À couvrir aussi pour `record` si on l'implémente.

**Référence** : `reference/umami/src/app/api/send/route.ts:131-133`.

## `/api/batch` compte les bots comme `processed` (2026-06-23, discovery)

**Découvert** : `batch/route.ts:39`.

**Symptôme** : dans un batch, un hit bot renvoie `{beep:'boop'}` en 200 → `response.ok` vrai → compté
dans `processed`, PAS dans `errors`. Donc `processed` n'égale PAS « réellement enregistrés ».

**Cause** : batch ré-invoque `send.POST` par item et ne teste que `response.ok`.

**Workaround** : ne pas se fier à `processed` comme preuve d'ingestion ; vérifier les stats (règle d'or
intégration : jamais d'assert sur le seul status). Pas de détection bot par-item exposée au client.

**Référence** : `reference/umami/src/app/api/batch/route.ts:36-43`.

## `export` renvoie un ZIP base64 dans du JSON, pas un binaire (2026-06-23, discovery)

**Découvert** : `websites/[websiteId]/export/route.ts`.

**Symptôme** : on pourrait attendre un `application/zip` binaire ; en réalité réponse
`application/json` = `{"zip": "<base64>"}` contenant un ZIP de 7 CSV.

**Workaround** : côté lib, parser le JSON, base64-décoder `zip`, puis dézipper (pas de gestion de
réponse binaire / `Accept` override comme SALOON_LIBRARY_DESIGN §7.3).

**Référence** : `reference/umami/src/app/api/websites/[websiteId]/export/route.ts:9`.

## `heartbeat`, `scripts/telemetry`, `share/[slug]` sont PUBLIC sans `parseRequest` (2026-06-23, discovery)

**Découvert** : ces 3 handlers n'appellent jamais `parseRequest` → un grep `skipAuth:true` les
classait « auth » à tort.

**Symptôme** : `GET /api/heartbeat` (sonde du seed) ne demande **aucune** auth et renvoie `{ok:true}`.

**Cause** : détection d'auth fiable = « pas de Bearer requis » SI (a) `skipAuth:true` OU (b) pas de
`parseRequest` du tout.

**Workaround** : marquer `SkipsAuth` ces endpoints côté lib. Inventaire d'auth = 8 publics au total
(`send`, `batch`, `record`, `login`, `config` + `heartbeat`, `scripts/telemetry`, `share/[slug]`).

**Référence** : `reference/umami/src/app/api/heartbeat/route.ts:1`.

## `logout` est un no-op sans Redis (token JWT stateless) (2026-06-23, discovery)

**Découvert** : `auth/logout/route.ts` + `lib/auth.ts`.

**Symptôme** : après `logout`, le Bearer reste valide si l'instance n'a pas Redis (le token est un JWT
signé stateless ; aucune révocation côté serveur).

**Workaround** : ne pas supposer qu'`logout()` invalide le token côté lib ; le consommateur gère le
cycle de vie. Pas de refresh non plus.

**Référence** : `reference/umami/src/app/api/auth/logout/route.ts:5`.

## Le User-Agent par défaut de la lib est flagué bot par Umami (2026-06-23, étape 7.1)

**Découvert** : test live d'un hit `/api/send` avec le UA du Connector
(`umami-php/1.0 (+https://github.com/AdrienGras/umami-php)`).

**Symptôme** : ce UA déclenche `isbot()` → **HTTP 200 `{"beep":"boop"}`** → le hit est filtré.
Donc tout `send`/`batch` qui ne fournit PAS de `userAgent` retombe sur le UA du Connector et est
**silencieusement filtré** (→ `BotFilteredException` côté lib).

**Cause** : `getClientInfo` (`lib/detect.ts:127`) résout `userAgent = payload?.userAgent ||
header('user-agent')`. Le UA du visiteur (payload) **prime** sur le header ; à défaut, le header
(UA lib) passe à `isbot`, qui rejette tout UA non-navigateur.

**Workaround / contrat** : en tracking serveur, **toujours relayer le vrai UA du visiteur** via
`Payload(userAgent: ...)` (ou les raccourcis `pageview/event/identify(..., userAgent: ...)`).
C'est un fail-safe sain (un hit sans UA visiteur EST suspect) mais à documenter dans le README.
Le UA du Connector ne sert que d'identité HTTP de la lib, jamais d'identité de tracking.

**Référence** : `reference/umami/src/lib/detect.ts:127` ; `src/Tracking/Payload.php` (phpdoc).

## Saloon v4 : `throw()` consulte `failed()`, pas `toException()` (2026-06-23, étape 5)

**Découvert** : lecture de `vendor/saloonphp/saloon/src/Http/Response.php` + `Traits/ManagesExceptions.php`.

**Symptôme** : la doc du pattern (`SALOON_LIBRARY_DESIGN.md` §4.4, écrite pour v3) suggère
d'override `toException()` avec `if ($this->failed())`. En v4, ça ne suffit PAS à requalifier un
**2xx** (le bot `beep/boop`) : `AlwaysThrowOnErrors` appelle `$response->throw()` →
`if (shouldThrowRequestException()) throw toException()`. `shouldThrowRequestException()` délègue
au Connector/Request (défaut `ManagesExceptions` = `$response->failed()`). Donc un 200 ne lève
jamais tant que `failed()` retourne `false`.

**Cause** : refonte v4 du pipeline d'exceptions (hooks `hasRequestFailed` / `getRequestException` /
`shouldThrowRequestException` côté Connector ET Request).

**Workaround** : la Response custom override **`failed()`** (`parent::failed() || isBotFiltered()`)
pour déclencher le throw, et **`createException()`** (protected) pour choisir le type
(`BotFilteredException` vs `UmamiApiException`). Cf. `docs/CONVENTIONS.md` (squelette Response v4).

**Référence** : `Response.php:381` (`failed`), `:476` (`throw`), `:439` (`toException`),
`:451` (`createException`) ; `ManagesExceptions.php`.

## `rtk` corrompt les commandes shell multi-lignes complexes (2026-06-23, étape 4)

**Découvert** : tests de validation anti-200 (curl + jq + heredoc + arithmétique `$(())`).

**Symptôme** : une commande bash multi-lignes passée au shell se fait mutiler à l'`eval`
(`parse error near 'NOW=$(($() AppleWebK...'` — des fragments de lignes distinctes se
retrouvent concaténés). Le shell hôte affiche aussi `[rtk] WARNING: untrusted project
filters (.rtk/filters.toml) — Filters NOT applied`.

**Cause** : l'intégration shell `rtk` (zsh) interfère avec l'évaluation des commandes
composées (substitutions, parenthèses dans des strings UA, heredocs).

**Workaround** : pour tout test non-trivial (plusieurs curl/jq, arithmétique, heredoc),
**écrire un `.sh` dans le scratchpad et l'exécuter via `bash chemin/script.sh`** plutôt que
d'inliner. Les commandes simples passent sans souci.

**Référence** : `scratchpad/validate.sh` (étape 4).

## `startAt`/`endAt` sont en epoch **millisecondes** (2026-06-23, confirmé live)

**Découvert** : validation live des stats (étape 4).

**Symptôme** : doute sur l'unité de `startAt`/`endAt` (s vs ms). Un appel `metrics`/`stats`
avec `startAt`/`endAt` en **millisecondes** (`$(date +%s) * 1000`) renvoie bien les hits ;
en secondes la fenêtre serait à côté.

**Cause** : Umami v3 manipule les bornes de plage en epoch ms.

**Workaround** : côté lib, exposer/convertir en ms pour les params `startAt`/`endAt`. (Le
`timestamp` du payload `/api/send`, lui, reste en epoch **secondes** — ne pas confondre.)

**Référence** : `docs/API_UMAMI.md` §2 (`@dateRange`).

## Deux contrats de date incohérents selon l'endpoint (2026-06-23, discovery)

**Découvert** : comparaison des schémas stats.

**Symptôme** : certaines routes (`withDateRange`) acceptent `startAt+endAt` (epoch) **OU**
`startDate+endDate` (date) ; d'autres (schéma zod brut : `events/series`, `sessions/stats`,
`sessions/weekly`, `event-data/*`, `session-data/*`) exigent **`startAt`+`endAt`** seulement, parfois
`timezone` requis. De plus, des GET déclarent `search` dans le handler sans l'avoir au schéma → le
param est silencieusement ignoré.

**Workaround** : ne pas généraliser un seul contrat de date ; mapper par endpoint (cf. table §4.3 de
`API_UMAMI.md`). Calibrer en live.

**Référence** : `docs/API_UMAMI.md` §2 (`@dateRange`) et §4.3.
