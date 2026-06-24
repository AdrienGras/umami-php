# API Umami v3.1.0 — précis vérifié (discovery)

> **Source de vérité** : clone `reference/umami/` au tag **v3.1.0** (`c78ff36`). Produit par la
> discovery du source (BOOTSTRAP étape 3), `find src/app/api -name route.ts` (**95 handlers**).
> Réfs `fichier:ligne` relatives à `reference/umami/`. Tout point non re-confirmé contre l'instance
> docker porte `⚠ à vérifier (live)` (casse des enums et `required` réellement appliqués surtout).
>
> Date : 2026-06-23.

---

## 0. Méthode & couverture

- **95 route handlers** listés exhaustivement (`§5`). Tous cartographiés (route, méthodes, auth).
- **5 endpoints PUBLIC** (sans Bearer) : `POST /api/send`, `POST /api/batch`, `POST /api/record`,
  `POST /api/auth/login`, `GET /api/config` (tous `skipAuth:true`) **+ 3 handlers sans `parseRequest`
  du tout** donc également publics : `GET /api/heartbeat`, `GET /api/scripts/telemetry`,
  `GET /api/share/[slug]`. Tout le reste exige `Authorization: Bearer <token>`.
- Profondeur de schéma maximale sur les domaines v1 (tracking, auth, stats, websites) ; catalogue +
  schémas principaux sur le reste.

## 1. Conventions transverses

Tout passe par `parseRequest(request, schema?, options?)` (`src/lib/request.ts:12`) :

- **Auth** : si `options.skipAuth !== true`, `checkAuth()` lit le header **`Authorization: Bearer <token>`**
  (`src/lib/auth.ts:11` → `getBearerToken` : `auth?.split(' ')[1]`). Échec → `401 unauthorized()`.
- **Validation entrée** : pour une requête **GET**, le `schema` zod valide les **query params** ;
  pour **non-GET**, il valide le **body JSON**. Échec → `400` avec `z.treeifyError(...)` (arbre
  d'erreurs zod, **pas** le format `{message,code,status}` standard).
- **Réponses succès** : `Response.json(data)` (HTTP 200). Helper `ok()` → `{"ok":true}`.
  Les listes paginées renvoient `{ data: [...], count, page, pageSize }` (✓ confirmé live étape 4
  sur `GET /api/websites`).
- **Réponses erreur** (`src/lib/response.ts`) : `{"error":{"message","code","status", ...}}` avec
  HTTP **400** (`bad-request`) / **401** (`unauthorized`) / **403** (`forbidden`) / **404** (`not-found`)
  / **500** (`server-error`).
- **Filtres suffixés** : les params query suffixés d'un nombre (`browser1`, `os2`) sont ré-injectés
  après Zod (`src/lib/request.ts:34`).

### Régime d'auth reporting (point sensible #3 — cf. §3.3)

`POST /api/auth/login` → `{ token, user }`. Le `token` est un **JWT « secure token » stateless**
(`createSecureToken`, `src/lib/jwt.ts`) — ou une clé Redis si Redis activé. Il se réinjecte sur
**toutes** les requêtes non-publiques via `Authorization: Bearer <token>`. **Pas de refresh** visible.
Le `logout` ne révoque rien sans Redis (no-op). ⚠ à vérifier (live) : durée de validité du token.

## 2. Référentiels d'enums (casse EXACTE — ⚠ à vérifier (live))

Depuis `src/lib/constants.ts` et `src/lib/schema.ts` :

- **Metric `type`** (`GET …/metrics` & `…/metrics/expanded`, routé par valeur, **400** si hors liste) :
  - `EVENT_COLUMNS` : `path`, `entry`, `exit`, `referrer`, `domain`, `title`, `query`, `event`,
    `tag`, `hostname`, `utmSource`, `utmMedium`, `utmCampaign`, `utmContent`, `utmTerm`
  - `SESSION_COLUMNS` : `browser`, `os`, `device`, `screen`, `language`, `country`, `city`, `region`,
    `distinctId`
  - spécial : `channel`. (Note v3 : plus de `type=url` de v2.)
- **`EVENT_TYPE`** : `pageView=1`, `customEvent=2`, `linkEvent=3`, `pixelEvent=4`, `performance=5`.
- **`COLLECTION_TYPE`** (champ `type` de `/api/send`) : `event`, `identify`, `performance`.
- **`ENTITY_TYPE`** (`shareType`) : `website=1`, `link=2`, `pixel=3`, `board=4`.
- **Rôles user** (`userRoleParam`) : `admin`, `user`, `view-only` (lu, fiable).
- **Rôles team** (`teamRoleParam`) : `team-member`, `team-view-only`, `team-manager` (lu, fiable).
- **`compare`** : `prev`, `yoy`. **`match`** : `all`, `any`. **`unit`** : `year`, `month`, `day`,
  `hour`, `minute`.
- **Operators** (segments) : `eq, neq, s, ns, c, dnc, re, nre, t, f, gt, lt, gte, lte, bf, af`.
- **`fieldsParam`** (values / breakdown) = EVENT_COLUMNS + SESSION_COLUMNS (sans `screen`).

### Params communs (factorisés)

- **`@dateRange`** (`withDateRange`, `schema.ts:16`) : au moins UNE paire requise — `startAt`+`endAt`
  (number, epoch **ms** — ✓ confirmé live étape 4) **OU** `startDate`+`endDate` (date). Optionnels :
  `timezone` (IANA), `unit`, `compare`.
- **`@filters`** (`filterParams`, `schema.ts:45`) : tous optionnels — colonnes EVENT/SESSION en string,
  `segment`/`cohort` (uuid), `eventType` (int>0), `excludeBounce` (string), `match` (`all|any`).
- **`@paging`** : `page` (int>0), `pageSize` (int>0). **`@search`** : `search` (string).
- ⚠ Plusieurs GET lisent `search` dans le handler sans l'avoir au schéma (`/api/reports`,
  `…/reports`, les `*/shares`) → le param peut être silencieusement ignoré. ⚠ à vérifier (live).

---

## 3. Points sensibles (architecture-critique)

### 3.1 `POST /api/send` — tracking, bot `beep/boop`, identify, cache

Réf : `src/app/api/send/route.ts`. **PUBLIC** (`skipAuth:true`, `:68`).

**Schéma body** (`:24`) : `{ type: 'event'|'identify'|'performance', payload: {...} }`.
`payload` exige **exactement un** de `website` / `link` / `pixel` (uuid) (`refine`, `:53`). Champs
optionnels notables : `hostname`(≤100), `language`(≤35), `referrer`, `screen`(≤11), `title`, `url`,
`name`(≤50, nom d'event custom), `tag`(≤50), `data` (objet, props d'event ou d'identify),
`id` (= **distinctId** pour identify), `timestamp` (epoch s), `ip`, `userAgent`, web-vitals
(`lcp/inp/cls/fcp/ttfb`).

**Filtre bot (point sensible #1 — CONFIRMÉ)** (`:131`) :
```ts
if (!process.env.DISABLE_BOT_CHECK && isbot(userAgent)) {
  return json({ beep: 'boop' });   // HTTP 200, body {"beep":"boop"}
}
```
→ **HTTP 200** avec body exactement `{"beep":"boop"}`. Signature confirmée — **✓ reproduit live
étape 4** : UA `curl/*` → `{"beep":"boop"}` ET absent des stats ; UA navigateur → enregistré. C'est
le déclencheur de `BotFilteredException` côté lib (la `Response` custom doit inspecter le body, cf.
CLAUDE.md règle 3). `isbot` se base sur le **User-Agent** → UA descriptif obligatoire côté serveur.
**✓ vérifié (live)** : `getClientInfo` (`lib/detect.ts:127`) résout `userAgent = payload?.userAgent
|| header('user-agent')` — le `userAgent` du **payload prime** sur le header. Un UA non-navigateur
(dont le UA par défaut de la lib) → `isbot` → `beep/boop`. Donc en tracking, relayer le UA du
**visiteur** via `payload.userAgent`. Réf `lib/detect.ts:127`.

**identify (point sensible #2)** : `type === 'identify'` + `data` → `saveSessionData` avec
`distinctId = payload.id` (`:272`). Le champ **`id`** rattache la session à un identifiant stable.

**Cache token (point sensible #2)** : header **`x-umami-cache`** en entrée (JWT signé, `:104`) →
évite le re-fetch website + recalcul session. **Réponse succès** (`:313`) :
`{ "cache": <token>, "sessionId": <uuid>, "visitId": <uuid> }` (✓ confirmé live étape 4). Le `cache`
est à renvoyer comme header `x-umami-cache` aux appels suivants. Session expirée après 30 min
d'inactivité (`:172`).

**Autres issues** : IP bloquée → `403 forbidden()` (`:136`) ; website introuvable → `400` (`:119`) ;
exception → `500`.

### 3.2 `POST /api/batch` — lot de hits

Réf : `src/app/api/batch/route.ts`. **PUBLIC** (`skipAuth:true`). **Schéma** : `z.array(anyObjectParam)`
— un **tableau** d'objets `{type, payload}` (mêmes que `/api/send`). Boucle et appelle `send.POST`
en interne pour chaque (`:36`). **Réponse** : `{ size, processed, errors, details: [{index, response}], cache }`.
⚠ Un hit bot renvoie `{beep:'boop'}` en 200 → compté comme **`processed`** (pas `errors`) mais **rien
n'est enregistré** (cf. QUIRKS).

### 3.3 `POST /api/auth/login`

Réf : `src/app/api/auth/login/route.ts`. **PUBLIC** (`skipAuth:true`). **Schéma body** :
`{ username: string, password: string }`. **Succès** (`:44`) :
`{ token, user: { id, username, role, createdAt, isAdmin, teams } }` (✓ confirmé live étape 4 :
`token` + `user.{username,role,isAdmin}`). **Échec** : `401`
`{error:{code:'incorrect-username-password', ...}}` → **✓ étape 7.2 : mappé en `UmamiApiException`**
(login mauvais mot de passe testé live). Le `token` alimente le Bearer (§1).

---

## 4. Domaines (cartographie détaillée)

### 4.1 Auth / Identity / Users / Teams / Admin

#### `POST /api/auth/logout`
- Auth Bearer. Entrée : aucune. Réponse `{ok:true}`. Token JWT **stateless** : révocation **seulement
  si Redis activé** (`redis.del`) ; sinon **no-op**, le token reste valide. Réf `auth/logout/route.ts:5`.

#### `POST /api/auth/verify`
- Auth Bearer. Entrée : aucune. Réponse : `auth.user` (+ `teams`). En `CLOUD_MODE`, ajoute
  `user.subscription`. **✓ étape 7.2 : le body EST l'objet user** (`{id,username,isAdmin,…}`,
  `username` confirmé live). ⚠ à vérifier (live) : présence/omission `password`. Réf `auth/verify/route.ts:6`.

#### `POST /api/auth/sso`
- Auth Bearer. Réponse `{user, token}` (nouveau token TTL 24 h). **Exige Redis** sinon `500
  "Redis is disabled"`. Réf `auth/sso/route.ts:6`.

#### `GET /api/me`
- Auth Bearer. Réponse : objet `auth` complet `{token, authKey, shareToken, user}`. Réf `me/route.ts:4`.

#### `POST /api/me/password`
- Auth Bearer. Body : `currentPassword` (requis), `newPassword` (requis, **min 8**). Réponse : user mis
  à jour. `400` si mot de passe actuel faux. Réf `me/password/route.ts:7`.

#### `GET /api/me/teams`
- Auth Bearer. Query `@paging`. Réponse : équipes de l'utilisateur (paginé). Réf `me/teams/route.ts:7`.

#### `POST /api/users` — ✅ vérifié live (étape 7.5, `UserEntrypoint::create`)
- Auth Bearer + `canCreateUser`. Body : `id` (uuid opt), `username` (requis, ≤255), `password`
  (requis, 8–255), `role` (requis ∈ `admin|user|view-only`). `400` si username pris. Réf `users/route.ts:11`.
- Live : `role` echoé en **lowercase** dans la réponse (`user`/`view-only`/`admin`). Le `password` **min 8**
  est **confirmé live** (étape consolidation 2026-06-24) : POST avec password <8 → `400 bad-request`
  `{password:["Too small: expected string to have >=8 characters"]}`. La garde côté lib est donc justifiée.

#### `GET POST DELETE /api/users/[userId]` — ✅ vérifié live (étape 7.5, `get`/`update`/`delete`)
- Auth Bearer (`canViewUser`/`canUpdateUser`/`canDeleteUser`). POST body : `username`(opt ≤255),
  `password`(opt 8–255), `role`(opt enum) — `role`/`username` appliqués seulement si admin. DELETE :
  `400 "You cannot delete yourself."` si auto-suppression. Réf `users/[userId]/route.ts:9/27/83`.
- Live : `update` n'envoie que les champs fournis ; `delete` renvoie `{}`. GET-after-delete non testé
  (contourné via `list()`) — comportement potentiellement ≠ du 200+null des websites.

#### `GET /api/users/[userId]/teams` · `GET /api/users/[userId]/websites` — ✅ vérifié live (étape 7.5, `teams`/`websites`)
- Auth Bearer (self OU admin). Query `@paging` (+ `search`, `includeTeams` pour websites). Réf
  `users/[userId]/teams/route.ts:7`, `users/[userId]/websites/route.ts:7`.
- Live : forme paginée `{data, count, page, pageSize}`. `websites` de l'owner contient bien le website seedé.

#### `GET /api/admin/users` — ✅ vérifié live (étape 7.5, `UserEntrypoint::list`) · `GET /api/admin/teams`
- Auth Bearer + `canViewUsers`/`canViewAllTeams`. Query `@paging` + `search`. Listes paginées triées
  `createdAt desc` (password omis). Réf `admin/users/route.ts:8`, `admin/teams/route.ts:8`.
- Live (`/api/admin/users` seul) : forme `{data, count, page, pageSize}`. `/api/admin/teams` non encore implémenté.

#### `GET POST /api/teams` — ✅ vérifié live (étape 7.6, `TeamEntrypoint::list`/`create`)
- Auth Bearer. GET : `@paging` → équipes du user. POST (+`canCreateTeam`) : `name` (requis, ≤50),
  `ownerId` (uuid opt) → team créé (`accessCode` généré). Réf `teams/route.ts:12/30`.
- ⚠ **Live (POST)** : renvoie un **tuple** `[team, ownerMembership]`, pas l'objet team. `create()` unwrap
  l'élément `[0]`. Cf. `QUIRKS.md`. GET renvoie `{data, count, page, pageSize}` (chaque team a `members`+`_count`).

#### `POST /api/teams/join` — ✅ vérifié live (étape 7.6, `TeamEntrypoint::join`)
- Auth Bearer. Body : `accessCode` (requis, ≤50). `404 team-not-found` / `400` déjà membre. Réf
  `teams/join/route.ts:7`. Live : retourne le `TeamUser` créé (`role: "team-member"`).

#### `GET POST DELETE /api/teams/[teamId]` — ✅ vérifié live (étape 7.6, `get`/`update`/`delete`)
- Auth Bearer (`canViewTeam`/`canUpdateTeam`/`canDeleteTeam`). POST body : `name`(opt ≤50),
  `accessCode`(opt ≤50). Réf `teams/[teamId]/route.ts:7/29/52`. Live : `update` renvoie l'objet team direct.

#### `GET POST /api/teams/[teamId]/users` · `GET POST DELETE /api/teams/[teamId]/users/[userId]` — ✅ vérifié live (étape 7.6)
- Auth Bearer. POST (ajout/maj) body : `userId` (uuid requis, ajout) + `role` (requis ∈
  `team-member|team-view-only|team-manager`). Réf `teams/[teamId]/users/route.ts:8/54`,
  `teams/[teamId]/users/[userId]/route.ts:8/29/60`. Live : `addMember`/`member`/`updateMember` renvoient
  l'objet `TeamUser` direct (`{id, teamId, userId, role}`).

#### `GET /api/teams/[teamId]/{websites,boards,links,pixels}`
- Auth Bearer + `canViewTeam`. Query `@paging` + `search`. Listes paginées. Réf
  `teams/[teamId]/{websites,boards,links,pixels}/route.ts:8`.
- ✅ `websites` vérifié live (étape 7.6, `TeamEntrypoint::websites`). `boards`/`links`/`pixels` déférés (BACKLOG).

#### `GET /api/heartbeat` — **PUBLIC** (pas de `parseRequest`)
- Réponse `{ok:true}`. ⚠ Aucune auth requise (corrige une intuition courante). Réf `heartbeat/route.ts:1`.

#### `GET /api/config` — **PUBLIC** (`skipAuth`)
- Réponse : `{cloudMode, faviconUrl, linksUrl, pixelsUrl, privateMode, telemetryDisabled,
  trackerScriptName, updatesDisabled, currentVersion}` (booléens dérivés d'env). Réf `config/route.ts:4`.

### 4.2 Websites & Sharing

#### `GET POST /api/websites`
- Auth Bearer. GET : `@paging` + `search` + `includeTeams` → liste paginée. POST : `name`(requis ≤100),
  `domain`(requis ≤500), `shareId`(opt ≤50 nullable), `teamId`(uuid opt), `id`(uuid opt). Réf
  `websites/route.ts:14/38`.

#### `GET POST DELETE /api/websites/[websiteId]`
- Auth Bearer (`canView/Update/DeleteWebsite`). POST body (tous opt) : `name`, `domain`,
  `shareId`(≤50 nullable, `null`→supprime shares), `replayEnabled`(bool), `replayConfig`
  (`{sampleRate 0..1, maskLevel strict|moderate, maxDuration int>0, blockSelector}`). DELETE →
  `{ok:true}`. `400 "That share ID is already taken."`. Réf `websites/[websiteId]/route.ts:16/37/106`.
  ✓ (live, étape 7.4) — QUIRK : GET sur un id supprimé retourne HTTP **200 + body `null`** (pas 404).
  Saloon lève `TypeError` (JSON `null` → `array $decodedJson`). À gérer côté appelant si besoin.

#### `POST /api/websites/[websiteId]/reset`
- Auth Bearer + `canUpdateWebsite`. Efface les données analytiques. Réponse `{ok:true}`. Réf
  `websites/[websiteId]/reset/route.ts:6`. ✓ (live, étape 7.4) — confirmé : reset() retourne void
  sans exception.

#### `POST /api/websites/[websiteId]/transfer`
- Auth Bearer. Body : `userId` (uuid opt) OU `teamId` (uuid opt) — l'un requis sinon `400`. Réf
  `websites/[websiteId]/transfer/route.ts:7`. ✓ (live, étape 7.4) — guard exactly-one confirmé.

#### `GET /api/websites/[websiteId]/daterange`
- Auth Bearer + `canViewWebsite`. Réponse : plage de dates des données. ✓ (live, étape 7.4)
  **Forme réelle** : `{startDate: ISO-string, endDate: ISO-string}` (≠ `{mindate,maxdate}` documenté
  initialement — corrigé). Réf `websites/[websiteId]/daterange/route.ts:6`.

#### `GET /api/websites/[websiteId]/values`
- Auth Bearer. Query `@dateRange` + `type` (requis, `fieldsParam`) + `search` (opt). ✓ (live, étape 7.4)
  **Forme réelle** : `[{value: string, count: int}]` (la doc mentionnait `[{value}]` seulement — `count`
  est aussi présent). Réf `websites/[websiteId]/values/route.ts:9`.

#### `GET /api/websites/[websiteId]/active`
- Auth Bearer + `canViewWebsite`. Réponse : visiteurs actifs (`[{visitors}]` ⚠ live). Réf
  `websites/[websiteId]/active/route.ts:6`.

#### `GET /api/realtime/[websiteId]`
- Auth Bearer + `canViewWebsite`. Pas de schéma (query = filtres libres). Fenêtre serveur = 30 min.
  Réf `realtime/[websiteId]/route.ts:8`.

#### `GET /api/admin/websites` · `GET /api/me/websites`
- Auth Bearer (`canViewAllWebsites` pour admin). Query `@paging` (+`search` admin ; `includeTeams` me).
  Listes paginées. Réf `admin/websites/route.ts:9`, `me/websites/route.ts:7`.

#### Sharing — `GET POST /api/websites/[websiteId]/shares`, `POST GET POST DELETE /api/share*`
- Auth Bearer (sauf `GET /api/share/[slug]` **PUBLIC**). `POST /api/share` body : `entityId`(uuid),
  `shareType`(int ENTITY_TYPE), `name`(≤200), `slug`(≤100 opt), `parameters`(objet). `GET /api/share/[slug]`
  (public) → `{shareId, shareType, parameters, token, <entityId selon type>, …}` ; `404` si inconnu.
  Réf `share/route.ts:10`, `share/[slug]/route.ts:47`, `share/id/[shareId]/route.ts:8/26/61`,
  `websites/[websiteId]/shares/route.ts:11/42`.

### 4.3 Stats / Metrics / Sessions / Events (toutes **GET**, Auth Bearer + `canViewWebsite`)

> Distinction clé : les routes en **`withDateRange`** acceptent `startAt+endAt` **OU**
> `startDate+endDate`. Les routes à **schéma brut** exigent `startAt`+`endAt` (epoch int), parfois
> `timezone` requis. Routes **sans schéma** = path params seuls.

| Endpoint | Entrée (query) | Réponse (champs) | Réf `:ligne` |
|---|---|---|---|
| `…/stats` | `@dateRange` + `@filters` | `{pageviews, visitors, visits, bounces, totaltime, comparison:{…}}` | `stats:8` |
| `…/metrics` | `type`(requis) + `limit,offset` + `@search,@dateRange,@filters` | `[{x, y}]` ; `400` si `type` inconnu | `metrics:14` |
| `…/metrics/expanded` | idem `/metrics` | version « expanded » ⚠ live | `metrics/expanded:14` |
| `…/pageviews` | `@dateRange` + `@filters` | `{pageviews:[{x,y}], sessions:[{x,y}]}` (+`compare`) | `pageviews:8` |
| `…/events` | `@dateRange,@filters,@paging,@search` | liste paginée d'events ⚠ live | `events:7` |
| `…/events/series` | `startAt,endAt,timezone` **requis** + `unit,limit,@filters` | série temporelle ⚠ live | `events/series:8` |
| `…/events/stats` | `@dateRange` + `@filters` | `{data:{events, visitors, visits, uniqueEvents, comparison:{…}}}` | `events/stats:8` |
| `…/sessions` | `@dateRange,@filters,@paging,@search` | liste paginée de sessions ⚠ live | `sessions:7` |
| `…/sessions/stats` | `startAt,endAt` **requis** + `@filters` | `{pageviews:{value}, visitors:{value}, visits:{value}, countries:{value}, events:{value}}` | `sessions/stats:8` |
| `…/sessions/weekly` | `startAt,endAt,timezone` **requis** + `@filters` | matrice jour×heure ⚠ live | `sessions/weekly:8` |
| `…/sessions/[sessionId]` | aucun schéma | détail session ⚠ live | `sessions/[sessionId]:6` |
| `…/sessions/[sessionId]/activity` | `startAt,endAt` **requis** | activité chronologique ⚠ live | `…/activity:7` |
| `…/sessions/[sessionId]/properties` | aucun schéma | `[{…}]` propriétés ⚠ live | `…/properties:6` |
| `…/sessions/[sessionId]/replays` | `@dateRange,@paging,@search` (pas `@filters`) | liste replays ⚠ live | `…/replays:7` |
| `…/event-data` | `startAt,endAt` **requis** + `@filters,@paging` | `{data:[{websiteId,eventId,eventName,eventProperties:[…]}], count, page, pageSize}` | `event-data:8` |
| `…/event-data/events` | `startAt,endAt` **requis** + `event?,@filters` | events + compteurs props ⚠ live | `event-data/events:8` |
| `…/event-data/fields` | `startAt,endAt` **requis** + `@filters` | `[{eventName,propertyName,dataType,…}]` ⚠ live | `event-data/fields:8` |
| `…/event-data/properties` | `startAt,endAt` **requis** + `@filters` | props agrégées ⚠ live | `event-data/properties:8` |
| `…/event-data/stats` | `startAt,endAt` **requis** + `@filters` | `{events, properties, records}` ⚠ live | `event-data/stats:8` |
| `…/event-data/values` | `startAt,endAt,event,propertyName` **requis** + `@filters` | `[{value, total}]` ⚠ live | `event-data/values:8` |
| `…/event-data/[eventId]` | aucun schéma | détail ⚠ live | `event-data/[eventId]:6` |
| `…/session-data/properties` | `startAt,endAt` **requis** + `@filters` | props session ⚠ live | `session-data/properties:8` |
| `…/session-data/values` | `startAt,endAt` **requis** + `propertyName?,@filters` | `[{value, total}]` ⚠ live | `session-data/values:8` |

### 4.4 Reports & Contenu — ✅ vérifié live (étape 7.7, `ReportEntrypoint`)

**Reports d'exécution** (`POST /api/reports/<type>`) — body = intersection `{websiteId(uuid),
filters}` + `{type, parameters}` (`reportResultSchema`, `schema.ts:293`). `parameters` validé par
`type`. Tous **Auth Bearer**. La plupart : `startDate`+`endDate` (date) requis dans `parameters`.

> **Live (étape 7.7)** : `filters` est **requis et doit être un objet** — l'omettre →
> `400 "expected object, received undefined"` ; `{}` accepté. La lib envoie donc toujours `filters`
> comme objet (`{}` si vide). **La forme de réponse varie par type** : `funnel` → **liste**
> `[{type,value,visitors,previous,dropped,dropoff,remaining}]` ; `utm` → **objet** `{utm_source,…}`.
> D'où le passthrough `asArray` (pas `asObject`) côté lib. Cf. `QUIRKS.md`.

| Report | `parameters` spécifiques | Réf |
|---|---|---|
| `attribution` | `model`(`first-click|last-click`), `type`(`path|event`), `step`, `currency?` | `reports/attribution:7` |
| `breakdown` | `fields` (array de `fieldsParam`) | `reports/breakdown:7` |
| `funnel` | `window`(>0), `steps` (2–8 ; chaque `{type:path|event, value, filters?}`) | `reports/funnel:7` |
| `goal` | `type`, `value` | `reports/goal:7` |
| `journey` | `steps`(2–7), `startStep?`, `endStep?`, `eventType?` | `reports/journey:7` |
| `performance` | `metric`(`lcp|inp|cls|fcp|ttfb`), `unit?`, `timezone?` → `{chart,summary,pages,…}` | `reports/performance:8` |
| `retention` | `timezone?` | `reports/retention:7` |
| `revenue` | `currency`(requis), `unit?`, `timezone?`, `compare?` → `{chart, total:{…}}` | `reports/revenue:10` |
| `utm` | (dates seules) → `{utm_source, utm_medium, utm_campaign, utm_term, utm_content}` | `reports/utm:8` |

**Reports CRUD** : `GET POST /api/reports` (POST body `reportSchema` : `websiteId, type, name(≤200),
description?(≤500), parameters`), `GET POST DELETE /api/reports/[reportId]`,
`GET /api/websites/[websiteId]/reports`. Réf `reports/route.ts:9/49`, `reports/[reportId]/route.ts:7/25/59`.
- ✅ vérifié live : `GET /api/reports` requiert `websiteId` en query ; POST retourne l'objet report
  (`description` renvoyée `""` si omise). `/api/websites/[websiteId]/reports` non implémenté (déféré, redondant
  avec `reports->list(websiteId)`).

**Boards / Links / Pixels** (Auth Bearer, CRUD + `/shares`) :
- `GET POST /api/boards` (POST : `type`(BOARD_TYPES), `name`≤100, `description?`, `userId?/teamId?`,
  `parameters?`), `GET POST DELETE /api/boards/[boardId]`, `GET POST /api/boards/[boardId]/shares`.
- `GET POST /api/links` (POST : `name`≤100, `url`≤500, `slug`≤100, `teamId?`, `id?`), `…/[linkId]`,
  `…/[linkId]/shares`.
- `GET POST /api/pixels` (POST : `name`≤100, `slug`≤100, `teamId?`, `id?`), `…/[pixelId]`, `…/[pixelId]/shares`.
- ⚠ à vérifier (live) : valeurs/casse de `BOARD_TYPES`.

**Segments / Cohorts** : `GET POST /api/websites/[websiteId]/segments` (`type`:`segment|cohort` requis ;
POST `parameters` = `{filters:[{name,operator,value}], match?, dateRange?, action?}`),
`GET POST DELETE …/segments/[segmentId]`. Réf `…/segments/route.ts:9/38`.

**Replays** (JSON, **pas** binaire) : `GET …/replays` (`@dateRange,@filters,@paging,@search`),
`GET …/replays/[replayId]` → `{sessionId, events:[…rrweb], startedAt, endedAt, eventCount, chunkCount}`,
`GET …/replays/saved`, `GET POST …/replays/saved/[replayId]` (POST `{isSaved(bool), name?}`).

**Export** : `GET /api/websites/[websiteId]/export` — `@dateRange` + `@paging`. **Réponse JSON**
`{"zip": <base64>}` (Content-Type `application/json`) : un ZIP base64 contenant 7 CSV
(`events, pages, referrers, browsers, os, devices, countries`). Le client doit base64-décoder puis
dézipper. **Pas** un binaire direct. Réf `…/export/route.ts:9`.

**Divers** : `GET /api/websites/[websiteId]/revenue/sessions` (`@dateRange` + `currency` requis),
`GET POST /api/dashboard` (board perso clé=userId), `GET /api/scripts/telemetry` (**PUBLIC**, renvoie
du **JavaScript** `text/javascript`).

### 4.5 Ingestion publique — `POST /api/record` (replays, **PUBLIC**)

`skipAuth:true` mais exige le header **`x-umami-cache`** (JWT session) : `400 "Missing/Invalid session
token."` sinon. Body : `{type:'record', payload:{website(uuid), events(array ≤200), timestamp?}}`.
Réponses 200 : `{ok:true}` / `{ok:false, reason:'replay_disabled'}` / **`{beep:'boop'}`** (bot) ;
`403` IP bloquée. ⚠ à vérifier (live) : structure d'un `event` (rrweb, `z.any()`). Réf `record/route.ts:27`.

---

## 5. Inventaire complet des 95 handlers

> Auth : `PUBLIC` = sans Bearer ; sinon Bearer requis. (`*` = corrigé vs grep : handlers sans `parseRequest`.)

| Route | Méthodes | Auth |
|---|---|---|
| `/api/admin/teams` | GET | Bearer |
| `/api/admin/users` | GET | Bearer |
| `/api/admin/websites` | GET | Bearer |
| `/api/auth/login` | POST | **PUBLIC** |
| `/api/auth/logout` | POST | Bearer |
| `/api/auth/sso` | POST | Bearer |
| `/api/auth/verify` | POST | Bearer |
| `/api/batch` | POST | **PUBLIC** |
| `/api/boards` · `/[boardId]` · `/[boardId]/shares` | GET,POST(,DELETE) | Bearer |
| `/api/config` | GET | **PUBLIC** |
| `/api/dashboard` | GET,POST | Bearer |
| `/api/heartbeat` | GET | **PUBLIC** \* |
| `/api/links` · `/[linkId]` · `/[linkId]/shares` | GET,POST(,DELETE) | Bearer |
| `/api/me` · `/me/password` · `/me/teams` · `/me/websites` | GET/POST | Bearer |
| `/api/pixels` · `/[pixelId]` · `/[pixelId]/shares` | GET,POST(,DELETE) | Bearer |
| `/api/realtime/[websiteId]` | GET | Bearer |
| `/api/record` | POST | **PUBLIC** |
| `/api/reports` · `/[reportId]` | GET,POST(,DELETE) | Bearer |
| `/api/reports/{attribution,breakdown,funnel,goal,journey,performance,retention,revenue,utm}` | POST | Bearer |
| `/api/scripts/telemetry` | GET | **PUBLIC** \* |
| `/api/send` | POST | **PUBLIC** |
| `/api/share` · `/share/id/[shareId]` | POST/GET/DELETE | Bearer |
| `/api/share/[slug]` | GET | **PUBLIC** \* |
| `/api/teams` · `/join` · `/[teamId]` (+ `/users`, `/users/[userId]`, `/websites`, `/boards`, `/links`, `/pixels`) | GET,POST,DELETE | Bearer |
| `/api/users` · `/[userId]` (+ `/teams`, `/websites`) | GET,POST,DELETE | Bearer |
| `/api/websites` · `/[websiteId]` | GET,POST,DELETE | Bearer |
| `/api/websites/[websiteId]/active` | GET | Bearer |
| `/api/websites/[websiteId]/daterange` | GET | Bearer |
| `/api/websites/[websiteId]/event-data` (+ `/events,/fields,/properties,/stats,/values,/[eventId]`) | GET | Bearer |
| `/api/websites/[websiteId]/events` (+ `/series,/stats`) | GET | Bearer |
| `/api/websites/[websiteId]/export` | GET | Bearer |
| `/api/websites/[websiteId]/metrics` (+ `/expanded`) | GET | Bearer |
| `/api/websites/[websiteId]/pageviews` | GET | Bearer |
| `/api/websites/[websiteId]/replays` (+ `/[replayId]`, `/saved`, `/saved/[replayId]`) | GET,POST | Bearer |
| `/api/websites/[websiteId]/reports` | GET | Bearer |
| `/api/websites/[websiteId]/reset` | POST | Bearer |
| `/api/websites/[websiteId]/revenue/sessions` | GET | Bearer |
| `/api/websites/[websiteId]/segments` (+ `/[segmentId]`) | GET,POST,DELETE | Bearer |
| `/api/websites/[websiteId]/session-data/{properties,values}` | GET | Bearer |
| `/api/websites/[websiteId]/sessions` (+ `/stats,/weekly,/[sessionId]` & sous-routes) | GET | Bearer |
| `/api/websites/[websiteId]/shares` | GET,POST | Bearer |
| `/api/websites/[websiteId]/stats` | GET | Bearer |
| `/api/websites/[websiteId]/transfer` | POST | Bearer |
| `/api/websites/[websiteId]/values` | GET | Bearer |

*(Détail ligne-à-ligne des 95 fichiers : `find reference/umami/src/app/api -name route.ts`.)*

---

## 6. Checklist de fin de discovery (BOOTSTRAP §3.3)

- [x] `find … route.ts` épuisé (95), chaque handler a une entrée (§4 détaillé pour v1, §5 exhaustif).
- [x] Régime d'auth identifié pour chaque domaine (5 `skipAuth` + 3 sans `parseRequest` = 8 publics ;
      reste Bearer).
- [x] Cas `beep/boop` documenté avec réf (`send/route.ts:131-133`, body `{"beep":"boop"}`, **aussi**
      `record/route.ts`).
- [x] Mécanique `identify` (`type:'identify'` + `id`/distinctId) + cache token (`x-umami-cache`,
      réponse `{cache,sessionId,visitId}`) documentée.
- [x] Mécanique d'auth reporting documentée (`Bearer`, JWT stateless, login `{token,user}`, pas de
      refresh, logout no-op sans Redis).
- [x] Points à reconfirmer en live marqués `⚠ à vérifier (live)` (casse enums, `required`, formes de
      réponse non tracées jusqu'au SQL).
