# Handoff — état courant du projet

Notes informelles à destination de la prochaine session (humaine ou Claude). Format libre, chronologique inverse (le plus récent en haut).

**À mettre à jour à la fin d'une session significative**. Pas besoin de noter chaque petit truc — l'idée est de te resituer en 30 secondes en début de session.

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
