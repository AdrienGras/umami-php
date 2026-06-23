# Backlog & idées

Choses identifiées comme "à faire un jour" mais pas prioritaires. Si tu trouves une amélioration en passant, note-la ici plutôt que de l'oublier ou de la coder maintenant.

Une fois faite, déplace-la en `INDEX.md` (livré) ou supprime-la (abandonnée).

---

## Bridge framework

- [ ] **Bridge Symfony** (non-objectif v1, cf. spec contrat) : binding DI, `HttpExceptionInterface`,
  `%env()%`. Hors de ce package — paquet séparé plus tard.

## Tooling / qualité

- [ ] Câbler `scripts/check.sh` en **git pre-commit hook** réel (`.git/hooks/pre-commit`) pour
  forcer la porte automatiquement, pas seulement par discipline.
- [ ] CI (GitHub Actions) rejouant la porte de validation + intégration docker.
- [ ] Re-vérifier le mécanisme `allowBaseUrlOverride` en Saloon v4 (durci suite à CVE-2026-33182)
  si un endpoint à hôte alternatif devient nécessaire.

## Domaines API (selon arbitrage — cf. spec contrat)

- [ ] `UserEntrypoint` / `TeamEntrypoint` / `ReportEntrypoint` : candidats BACKLOG si hors usage immédiat.

## Découvertes discovery à traiter à l'implémentation

- [ ] **`record` (replays)** : endpoint public porteur lui aussi du 200 `{"beep":"boop"}` + exige le
  header `x-umami-cache`. Si on couvre les replays, `BotFilteredException` doit s'y appliquer aussi.
- [ ] **`export`** : réponse `{"zip": "<base64>"}` (JSON, pas binaire) → prévoir un helper base64→unzip
  côté Entrypoint (≠ pattern réponse binaire SALOON_LIBRARY_DESIGN §7.3).
- [ ] Vérifier en **live** (étape 4) les formes de réponse marquées `⚠ à vérifier (live)` non tracées
  jusqu'au SQL (event-data/*, session-data/*, sessions détail).
