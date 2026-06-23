# umami-php — client PHP transport-only pour l'API Umami Analytics

`umami-php` est un **package Composer autonome** (PHP pur, aucune dépendance framework) qui
expose l'**API complète d'Umami v3.1.0** — tracking (`/api/send`, `/api/batch`) ET
reporting/admin (auth, websites, stats, metrics, events, sessions, users, teams, reports) —
via la librairie [Saloon v4](https://docs.saloon.dev/), selon le pattern **transport-only**
maison (cf. `docs/SALOON_LIBRARY_DESIGN.md`).

Langue de travail : **français** (docs, commits, commentaires de spec). Le code et la
phpdoc publique sont en **anglais** (package destiné à Packagist).

## Identité du package

- Nom Composer : `adriengras/umami-php`
- Namespace PSR-4 : `AdrienGras\Umami\`
- Dépôt : https://github.com/AdrienGras/umami-php
- PHP : ^8.2 · Saloon : ^4.0 · Licence : MIT
- Version Umami cible : **3.1.0** (la self-hostée en prod)

## Layout du repo

```
umami-php/
├── CLAUDE.md                  ← ce fichier
├── BOOTSTRAP.md               ← setup initial (session 1 uniquement)
├── composer.json
├── .mcp.json                  ← serveur Context7 (Saloon indexable)
├── scripts/
│   └── clone-references.sh    ← clone gitignoré du source Umami
├── src/                       ← la lib (créée par le bootstrap, cf. SALOON_LIBRARY_DESIGN §3)
├── tests/                     ← Unit + Integration (instance Umami docker)
├── reference/                 ← clones de référence, EN GITIGNORE (voir ci-dessous)
└── docs/
    ├── SALOON_LIBRARY_DESIGN.md  ← LE pattern (fourni, NE PAS modifier sans raison)
    ├── API_UMAMI.md              ← précis API, PRODUIT par la discovery (bootstrap étape 3)
    ├── HANDOFF.md / INDEX.md / ENVIRONMENT.md / QUIRKS.md / BACKLOG.md / CONVENTIONS.md
    └── superpowers/specs|plans/
```

## Références locales (`reference/`, gitignoré)

Clones présents localement, **non commités**. **Source de vérité** — toujours préférés à
la doc en ligne et à ta connaissance d'entraînement :

| Dossier | Contenu | Usage |
|---|---|---|
| `reference/umami/` | Source Umami, **tag v3.1.0** | LA référence pour TOUTE route : méthode, auth, schéma zod, forme de réponse. Greppe `src/app/api/**/route.ts`, ne déduis pas. |
| `reference/saloon/` *(optionnel)* | Source Saloon v4 | Arbitre des signatures/traits. **Première intention : MCP Context7** ; le clone tranche les doutes de niveau source. |

S'ils sont absents : `bash scripts/clone-references.sh` (voir BOOTSTRAP.md).

## Règles d'or (toujours applicables)

1. **Le source bat la doc.** Toute route de l'API Umami se vérifie dans `reference/umami/`
   (`src/app/api/**/route.ts` + `src/tracker/index.js`) avant d'écrire une `Request`.
   `docs/API_UMAMI.md` est produit PAR la discovery du source (bootstrap étape 3), pas
   l'inverse. Tout point non encore vérifié porte `⚠ à vérifier (live)` jusqu'à confirmation.

2. **Transport-only (règle n°1 du pattern Saloon).** La lib ne connaît AUCUNE entité métier,
   ne persiste rien, ne décide rien. Transformations/normalisations/gardes dans l'**Entrypoint** ;
   la `Request` est stupide ; le `Connector` est l'infra. Cf. `docs/SALOON_LIBRARY_DESIGN.md` §1-2.

3. **`AlwaysThrowOnErrors` partout** + **un 200 ne prouve rien.** Le filtre bot d'Umami renvoie
   un **200** avec body `{"beep":"boop"}` sur `/api/send` et `/api/batch` — Saloon le considère
   `successful()` et NE lève PAS. La `Response` custom DOIT inspecter le body et requalifier ce
   cas en **`BotFilteredException`** (sous-type de `UmamiApiException`). C'est le seul endroit où
   un 2xx devient une erreur. ⚠ Vérifier la signature exacte du body contre le source
   (`send/route.ts` autour du check `isbot`) à l'implémentation.

4. **PHP pur, zéro framework.** Pas de `HttpExceptionInterface` (Symfony), pas de binding
   `services.yaml`, pas de `%env()%`. Le Connector s'instancie explicitement avec ses valeurs
   résolues (`new UmamiApi(baseUrl: ..., apiToken: ...)`). Un éventuel bridge Symfony viendra
   plus tard, hors de ce package (cf. BACKLOG).

5. **Deux régimes d'auth distincts.**
   - **Tracking** (`/api/send`, `/api/batch`) : `skipAuth` côté serveur → AUCUN Bearer.
     User-Agent descriptif **obligatoire** (sinon flag bot). Côté PHP serveur, le UA est
     librement définissable (pas de forbidden-header comme sur le web).
   - **Reporting/admin** : `POST /api/auth/login` → token Bearer, réinjecté sur toutes les
     autres requêtes. ⚠ Mécanique exacte (header, durée, refresh) à vérifier au source.

6. **Le contrat live est la source de vérité** (pattern §8 piège n°7). La casse des valeurs
   d'enum, les `required`, les formats : confirmés contre l'instance docker qui tourne, pas
   contre un export statique. Les `required` zod du source peuvent être plus stricts que ce
   que l'API applique réellement — calibrer les gardes sur le comportement réel.

7. **`DISABLE_BOT_CHECK` interdit** sur l'instance de test : le filtre bot actif fait partie
   de ce qu'on valide (le cas `beep/boop` de la règle 3).

8. **Porte de validation avant chaque commit (NON-NÉGOCIABLE).** Avant tout `git commit`,
   `bash scripts/check.sh` DOIT passer **vert**. Cette porte enchaîne : `composer validate`,
   `composer audit` (**zéro CVE** — c'est ainsi qu'on a tranché Saloon v3→v4), `php-cs-fixer`
   (dry-run), `phpstan`, et `phpunit --testsuite unit`. **Aucun commit si un seul échoue** —
   on corrige avant. Les tests d'**intégration** (docker) ne font PAS partie de cette porte
   (ils requièrent l'instance Umami) : ils se lancent séparément quand on touche au code réseau.

9. **Commits en gitmoji (toujours).** Tout message de commit commence par un gitmoji pertinent,
   suivi d'un sujet concis en français. Repères : 🎉 init projet · ✨ feature · 🐛 fix ·
   ♻️ refactor · ✅ tests · 📝 docs · 🔧 config/tooling · 🔒️ sécurité · 🚧 WIP · ⬆️ bump deps.
   La porte de validation (règle 8) reste un prérequis avant chaque commit.

## Pièges Saloon à garder en tête (détail dans `docs/SALOON_LIBRARY_DESIGN.md` §8)

- `AlwaysThrowOnErrors` + probe booléen → l'appelant doit `try/catch` (pas de `false`).
- Collision `$body` avec `HasJsonBody` → nommer le payload `$payload`.
- `Request::resolveBaseUrl()` ignoré par Saloon → URL absolue + `allowBaseUrlOverride` pour un hôte alternatif. ⚠ à vérifier v4 : la surcharge d'URL absolue était la faille SSRF CVE-2026-33182, durcie en v4 — re-confirmer le mécanisme exact au source/Context7 avant usage.
- Multipart : `value` = resource ouverte, jamais une string (non pertinent ici a priori, mais à l'esprit).

## Lancer / tester

```bash
bash scripts/clone-references.sh                  # clone umami@v3.1.0 dans reference/
docker compose -f docker-compose.test.yml up -d   # instance Umami de test (cf. bootstrap)
bash scripts/seed-umami.sh                        # crée le website de test, écrit .env.test
composer install
vendor/bin/phpunit --testsuite unit               # tests unitaires (factories, mapping erreur)
vendor/bin/phpunit --testsuite integration        # contre l'instance docker
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run

bash scripts/check.sh                             # PORTE DE VALIDATION (règle d'or 8) — verte obligatoire avant commit
```

Détails dans `docs/ENVIRONMENT.md` (rempli au bootstrap).

## Definition of done (toute feature / tout Entrypoint)

- Domaine vérifié au source (`reference/umami/`), marqueur `⚠ à vérifier (live)` retiré.
- Une `Request` par appel HTTP ; transformations dans l'Entrypoint ; optionnels nuls omis.
- Mapping d'erreur via la Response custom ; cas spéciaux (bot 200) couverts.
- Tests unit (factories/mapping) + intégration (contre docker, jamais d'assert sur le seul status).
- phpstan sans erreur, php-cs-fixer appliqué, phpdoc sur l'API publique.
- **Règle de fin d'implémentation du bloc mémoire appliquée.**

## Mémoire projet — où chercher quoi

En début de session, scanner `docs/` pour se resituer :

- **`docs/HANDOFF.md`** — état courant, dernière chose faite. **À lire en premier.**
- **`docs/INDEX.md`** — catalogue des Entrypoints/Requests livrés.
- **`docs/ENVIRONMENT.md`** — paths, services, ports, `.env.test`.

Au cas par cas : **`docs/QUIRKS.md`** (pièges), **`docs/BACKLOG.md`** (idées), **`docs/CONVENTIONS.md`**
(squelettes), **`docs/superpowers/specs|plans/`** (design & plans), **`docs/SALOON_LIBRARY_DESIGN.md`**
(le pattern, immuable), **`docs/API_UMAMI.md`** (précis API, vivant).

### À mettre à jour DURANT la session (une question = un fichier)

| Tu découvres ou décides… | Fichier |
|---|---|
| Une règle qui s'applique TOUJOURS au projet | `CLAUDE.md` |
| Un endpoint vérifié / un écart au source | `docs/API_UMAMI.md` |
| Un squelette de code récurrent | `docs/CONVENTIONS.md` |
| Un Entrypoint/Request livré | une ligne dans `docs/INDEX.md` + spec/plan si non-trivial |
| Un path, port, service, accès | `docs/ENVIRONMENT.md` |
| Un comportement non-évident, un piège | `docs/QUIRKS.md` (dès la découverte) |
| Une idée future | `docs/BACKLOG.md` |
| L'état mental d'une session | `docs/HANDOFF.md` (en fin de session) |

### Règle de fin d'implémentation (NON-NÉGOCIABLE)

À la fin de toute implémentation significative (Entrypoint livré, refactor majeur, bug fix
non-trivial), **avant de signaler la fin du travail**, tu DOIS :

1. **`docs/INDEX.md`** — ajouter la ligne du domaine/Request livré.
2. **`docs/HANDOFF.md`** — entrée datée en haut : `Dernière chose faite`, `Trucs en suspens`,
   `Prochaine chose à creuser`, `Notes pour future Claude`.
3. **`docs/API_UMAMI.md`** — consigner les routes vérifiées et retirer les `⚠ à vérifier (live)` levés.
4. **`docs/QUIRKS.md`** si un piège a été découvert.
5. **`docs/BACKLOG.md`** si des améliorations ont été identifiées mais non faites.
6. **`docs/CONVENTIONS.md`** si un nouveau pattern réutilisable a été introduit.
7. **`CLAUDE.md`** si une règle permanente a été établie.

Une feature livrée sans mise à jour de la mémoire est une feature à moitié livrée.

<!-- rtk-instructions v2 -->
# RTK (Rust Token Killer) - Token-Optimized Commands

## Golden Rule

**Always prefix commands with `rtk`**. If RTK has a dedicated filter, it uses it. If not, it passes through unchanged. This means RTK is always safe to use.

**Important**: Even in command chains with `&&`, use `rtk`:
```bash
# ❌ Wrong
git add . && git commit -m "msg" && git push

# ✅ Correct
rtk git add . && rtk git commit -m "msg" && rtk git push
```

## RTK Commands by Workflow

### Build & Compile (80-90% savings)
```bash
rtk cargo build         # Cargo build output
rtk cargo check         # Cargo check output
rtk cargo clippy        # Clippy warnings grouped by file (80%)
rtk tsc                 # TypeScript errors grouped by file/code (83%)
rtk lint                # ESLint/Biome violations grouped (84%)
rtk prettier --check    # Files needing format only (70%)
rtk next build          # Next.js build with route metrics (87%)
```

### Test (60-99% savings)
```bash
rtk cargo test          # Cargo test failures only (90%)
rtk go test             # Go test failures only (90%)
rtk jest                # Jest failures only (99.5%)
rtk vitest              # Vitest failures only (99.5%)
rtk playwright test     # Playwright failures only (94%)
rtk pytest              # Python test failures only (90%)
rtk rake test           # Ruby test failures only (90%)
rtk rspec               # RSpec test failures only (60%)
rtk test <cmd>          # Generic test wrapper - failures only
```

### Git (59-80% savings)
```bash
rtk git status          # Compact status
rtk git log             # Compact log (works with all git flags)
rtk git diff            # Compact diff (80%)
rtk git show            # Compact show (80%)
rtk git add             # Ultra-compact confirmations (59%)
rtk git commit          # Ultra-compact confirmations (59%)
rtk git push            # Ultra-compact confirmations
rtk git pull            # Ultra-compact confirmations
rtk git branch          # Compact branch list
rtk git fetch           # Compact fetch
rtk git stash           # Compact stash
rtk git worktree        # Compact worktree
```

Note: Git passthrough works for ALL subcommands, even those not explicitly listed.

### GitHub (26-87% savings)
```bash
rtk gh pr view <num>    # Compact PR view (87%)
rtk gh pr checks        # Compact PR checks (79%)
rtk gh run list         # Compact workflow runs (82%)
rtk gh issue list       # Compact issue list (80%)
rtk gh api              # Compact API responses (26%)
```

### JavaScript/TypeScript Tooling (70-90% savings)
```bash
rtk pnpm list           # Compact dependency tree (70%)
rtk pnpm outdated       # Compact outdated packages (80%)
rtk pnpm install        # Compact install output (90%)
rtk npm run <script>    # Compact npm script output
rtk npx <cmd>           # Compact npx command output
rtk prisma              # Prisma without ASCII art (88%)
```

### Files & Search (60-75% savings)
```bash
rtk ls <path>           # Tree format, compact (65%)
rtk read <file>         # Code reading with filtering (60%)
rtk grep <pattern>      # Search grouped by file (75%). Format flags (-c, -l, -L, -o, -Z) run raw.
rtk find <pattern>      # Find grouped by directory (70%)
```

### Analysis & Debug (70-90% savings)
```bash
rtk err <cmd>           # Filter errors only from any command
rtk log <file>          # Deduplicated logs with counts
rtk json <file>         # JSON structure without values
rtk deps                # Dependency overview
rtk env                 # Environment variables compact
rtk summary <cmd>       # Smart summary of command output
rtk diff                # Ultra-compact diffs
```

### Infrastructure (85% savings)
```bash
rtk docker ps           # Compact container list
rtk docker images       # Compact image list
rtk docker logs <c>     # Deduplicated logs
rtk kubectl get         # Compact resource list
rtk kubectl logs        # Deduplicated pod logs
```

### Network (65-70% savings)
```bash
rtk curl <url>          # Compact HTTP responses (70%)
rtk wget <url>          # Compact download output (65%)
```

### Meta Commands
```bash
rtk gain                # View token savings statistics
rtk gain --history      # View command history with savings
rtk discover            # Analyze Claude Code sessions for missed RTK usage
rtk proxy <cmd>         # Run command without filtering (for debugging)
rtk init                # Add RTK instructions to CLAUDE.md
rtk init --global       # Add RTK to ~/.claude/CLAUDE.md
```

## Token Savings Overview

| Category | Commands | Typical Savings |
|----------|----------|-----------------|
| Tests | vitest, playwright, cargo test | 90-99% |
| Build | next, tsc, lint, prettier | 70-87% |
| Git | status, log, diff, add, commit | 59-80% |
| GitHub | gh pr, gh run, gh issue | 26-87% |
| Package Managers | pnpm, npm, npx | 70-90% |
| Files | ls, read, grep, find | 60-75% |
| Infrastructure | docker, kubectl | 85% |
| Network | curl, wget | 65-70% |

Overall average: **60-90% token reduction** on common development operations.
<!-- /rtk-instructions -->