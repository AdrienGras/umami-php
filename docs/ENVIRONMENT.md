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
- **Tests d'intégration** : nécessitent une instance Umami de test en Docker (BOOTSTRAP étape 4,
  **pas encore en place**).

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

## Services actifs

| Service | Rôle | Accès |
|---|---|---|
| Instance Umami de test | cible des tests d'intégration | `docker-compose.test.yml` **présent** (Postgres 16-alpine + `ghcr.io/umami-software/umami:3.1.0`, port **3015**, `DISABLE_BOT_CHECK` interdit). ⚠ pas encore `up` ni seedée (`scripts/seed-umami.sh` à créer, étape 4). |

## Variables d'environnement

- `.env.test` (hors git) — config de l'instance Umami de test, écrite par `scripts/seed-umami.sh` (à créer, étape 4).
- `.env.local` (hors git) — secrets et overrides éventuels.

| Bloc | Variables |
|---|---|
| `###> umami-test ###` | `UMAMI_TEST_BASE`, `UMAMI_TEST_WEBSITE_ID`, `UMAMI_TEST_USERNAME`, `UMAMI_TEST_PASSWORD` ⚠ à créer (étape 4) |

## Références locales (gitignorées)

- `reference/umami/` — source Umami `@v3.1.0`, **source de vérité** des routes. Absent tant que
  `scripts/clone-references.sh` n'a pas tourné.
- **MCP Context7** (`.mcp.json`) — doc Saloon en première intention (`resolve-library-id` → `query-docs`).
