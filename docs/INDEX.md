# Registre des features livrées

Catalogue chronologique de ce qui a été construit. Pour chaque entrée : spec (le quoi/pourquoi), plan (le comment), statut. Une ligne par feature.

Voir aussi : `ENVIRONMENT.md` · `QUIRKS.md` · `BACKLOG.md` · `HANDOFF.md` · `superpowers/specs/` · `superpowers/plans/`

---

## Features

| Feature | Date | Spec | Plan | Statut | Notes |
|---|---|---|---|---|---|
| **Discovery API Umami v3.1.0** | 2026-06-23 | `superpowers/specs/2026-06-23-contrat-lib.md` | — | ✅ livré | `docs/API_UMAMI.md` : 95 handlers cartographiés, 8 publics, 3 points sensibles, checklist 3.3 cochée. Reste `⚠ à vérifier (live)` (étape 4 docker). |
| _(aucun Entrypoint/Request livré — implémentation à partir de BOOTSTRAP étape 5)_ | | | | | |

> Les Entrypoints cibles (Tracking, Auth, Stats, Website, …) sont décrits dans
> `superpowers/specs/2026-06-23-contrat-lib.md`. Ordre d'implémentation : BOOTSTRAP étape 7.

## Commandes / scripts utilitaires

| Commande | Date | Cible |
|---|---|---|
| `bash scripts/check.sh` | 2026-06-23 | Porte de validation pré-commit (règle d'or 8) : composer validate/audit + cs-fixer + phpstan + phpunit unit |
| `bash scripts/clone-references.sh` | 2026-06-23 | Clone gitignoré du source Umami `@v3.1.0` dans `reference/` (base de la discovery) |
