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
