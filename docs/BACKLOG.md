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

- [x] **`WebsiteEntrypoint`** ✅ livré (étape 7.4) : CRUD (`list/get/create/update/delete`) +
  sous-routes (`reset/transfer/dateRange/values`), 17 tests unit + 4 intégration.
- [ ] `UserEntrypoint` / `TeamEntrypoint` / `ReportEntrypoint` : candidats BACKLOG si hors usage immédiat.

## Sous-routes Website non couvertes (étape 7.4 — déféré)

- [ ] `GET /api/websites/[id]/active` — visiteurs actifs (couvert indirectement via `StatsEntrypoint::active()`).
- [ ] `GET /api/realtime/[id]` — fenêtre 30 min, pas de schéma de query.
- [ ] `GET POST /api/websites/[id]/shares` + `POST GET POST DELETE /api/share*` — partage par slug.
- [ ] `GET /api/websites/[id]/export` — réponse `{"zip":"<base64>"}` (cf. découverte export BACKLOG).
- [ ] `GET POST DELETE /api/websites/[id]/segments` — segmentation.
- [ ] `GET POST /api/websites/[id]/event-data/*` — données événements custom.
- [ ] `GET /api/websites/[id]/session-data/*` — données session.
- [ ] `GET /api/websites/[id]/revenue` — revenus.

## Reste à faire sur les domaines livrés (Tracking / Auth / Stats)

- [ ] **README / quickstart** (critère d'acceptation v1.0.0) : tracking (note **UA visiteur
  obligatoire** + `try/catch BotFilteredException`), auth+reporting, note `logout` no-op sans Redis,
  note probe booléen → `try/catch`.
- [ ] **Sous-routes Stats** non couvertes : `metrics/expanded`, `events/series`, `events/stats`,
  `sessions/stats|weekly|[sessionId]` (+ activity/properties/replays), `event-data/*`,
  `session-data/*`. À ajouter au `StatsEntrypoint` selon besoin.
- [ ] **DTO de sortie typés** (au lieu d'`array` brut) pour Stats/Auth si la DX le justifie
  (factory pure dans `Utils/`, cf. SALOON_LIBRARY_DESIGN §7.1).
- [ ] **Enums `Unit` / `Compare`** pour `Period` (actuellement `?string` : `unit` ∈ year/month/day/
  hour/minute ; `compare` ∈ prev/yoy) — cohérence avec la convention « enums backed ».
- [ ] **`identify`** : test d'intégration de rattachement distinctId complet (via API sessions) —
  actuellement smoke (200 accepté).

## Découvertes discovery à traiter à l'implémentation

- [ ] **`record` (replays)** : endpoint public porteur lui aussi du 200 `{"beep":"boop"}` + exige le
  header `x-umami-cache`. Si on couvre les replays, `BotFilteredException` doit s'y appliquer aussi.
- [ ] **`export`** : réponse `{"zip": "<base64>"}` (JSON, pas binaire) → prévoir un helper base64→unzip
  côté Entrypoint (≠ pattern réponse binaire SALOON_LIBRARY_DESIGN §7.3).
- [ ] Vérifier en **live** (étape 4) les formes de réponse marquées `⚠ à vérifier (live)` non tracées
  jusqu'au SQL (event-data/*, session-data/*, sessions détail).
