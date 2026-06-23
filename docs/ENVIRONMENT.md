# Environnement du projet

Carte des paths, conteneurs, services, accès. À jour au fil des découvertes.

À consulter **avant de lancer toute commande non-triviale**.

---

## Repo

- **Path hôte** : `/srv/AdrienGras/umami-php`
- **Branche par défaut** : `main`
- **Convention de merge** : ⚠ à confirmer avec Adrien (par défaut supposé : feature branches + PR,
  package destiné à Packagist).

## Stack d'exécution

- **Runtime** : local sur l'hôte (PHP **8.5.7** + Composer). Pas de conteneur pour la lib elle-même.
  ⚠ Le package cible `^8.2` : `php-cs-fixer` warne (8.5 > 8.2), sans impact sur la porte.
- **Comment lancer une commande** : directement en local (`php`, `composer`, `vendor/bin/*`).
- **Tests d'intégration** : instance Umami de test en Docker (BOOTSTRAP étape 4, **en place**).
  Lancer : `docker compose -f docker-compose.test.yml up -d` puis `bash scripts/seed-umami.sh`.

## Commandes principales

| But | Commande |
|---|---|
| Installer les deps | `composer install` |
| **Porte de validation (pré-commit)** | `bash scripts/check.sh` |
| Tests unitaires | `vendor/bin/phpunit --testsuite unit` |
| Tests d'intégration (docker requis) | `vendor/bin/phpunit --testsuite integration` |
| Analyse statique | `vendor/bin/phpstan analyse` |
| Style (vérif) | `vendor/bin/php-cs-fixer fix --dry-run` |
| Style (correction) | `vendor/bin/php-cs-fixer fix` |
| Audit sécurité | `composer audit` |
| Cloner le source Umami (discovery) | `bash scripts/clone-references.sh` |
| Démarrer l'instance de test | `docker compose -f docker-compose.test.yml up -d` |
| Seeder l'instance (→ `.env.test`) | `bash scripts/seed-umami.sh` (idempotent) |
| Repartir d'une base vierge | `docker compose -f docker-compose.test.yml down -v` puis up + seed |

## Services actifs

| Service | Rôle | Accès |
|---|---|---|
| Instance Umami de test | cible des tests d'intégration | `docker-compose.test.yml` (Postgres 16-alpine + `ghcr.io/umami-software/umami:3.1.0`, port **3015**, `DISABLE_BOT_CHECK` interdit). **Up + seedée** via `scripts/seed-umami.sh`. Admin par défaut `admin`/`umami` (créé au 1er boot, cf. `reference/umami/scripts/seed/index.ts:129`). Readiness fiable = **login réussi** (le heartbeat répond 200 avant les migrations). |

## Variables d'environnement

- `.env.test` (hors git) — config de l'instance Umami de test, **(ré)écrite par `scripts/seed-umami.sh`** (ne pas éditer à la main). Surcharge possible des valeurs par export avant de lancer le seed (cf. en-tête du script).
- `.env.local` (hors git) — secrets et overrides éventuels.

| Bloc | Variables |
|---|---|
| `###> umami-test ###` | `UMAMI_TEST_BASE` (`http://localhost:3015`), `UMAMI_TEST_WEBSITE_ID` (UUID du website `umami-php-test`), `UMAMI_TEST_HOSTNAME` (`umami-php.test`), `UMAMI_TEST_USERNAME` (`admin`), `UMAMI_TEST_PASSWORD` (`umami`) |

## Références locales (gitignorées)

- `reference/umami/` — source Umami `@v3.1.0`, **source de vérité** des routes. Absent tant que
  `scripts/clone-references.sh` n'a pas tourné.
- **MCP Context7** (`.mcp.json`) — doc Saloon en première intention (`resolve-library-id` → `query-docs`).
