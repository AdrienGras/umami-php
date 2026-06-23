#!/usr/bin/env bash
#
# seed-umami.sh — prépare l'instance Umami de test pour les tests d'intégration.
#
# Idempotent : attend que l'instance soit prête (login réussi, pas seulement
# heartbeat), réutilise le website de test s'il existe déjà (GET avant POST),
# sinon le crée, puis (ré)écrit .env.test avec les coordonnées résolues.
#
# Pré-requis hôte : curl, jq. L'instance docker doit tourner
# (docker compose -f docker-compose.test.yml up -d).
#
# Variables surchargeables par l'environnement :
#   UMAMI_TEST_BASE      (défaut http://localhost:3015)
#   UMAMI_TEST_USERNAME  (défaut admin)   — admin par défaut créé au 1er boot
#   UMAMI_TEST_PASSWORD  (défaut umami)   — cf. reference/umami scripts/seed/index.ts
#   UMAMI_TEST_WEBSITE_NAME  (défaut umami-php-test)
#   UMAMI_TEST_HOSTNAME      (défaut umami-php.test) — domaine du website
#
set -euo pipefail

BASE="${UMAMI_TEST_BASE:-http://localhost:3015}"
USERNAME="${UMAMI_TEST_USERNAME:-admin}"
PASSWORD="${UMAMI_TEST_PASSWORD:-umami}"
WEBSITE_NAME="${UMAMI_TEST_WEBSITE_NAME:-umami-php-test}"
HOSTNAME_="${UMAMI_TEST_HOSTNAME:-umami-php.test}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$(cd "$SCRIPT_DIR/.." && pwd)/.env.test"

log() { printf '\033[36m[seed]\033[0m %s\n' "$*"; }
die() { printf '\033[31m[seed:erreur]\033[0m %s\n' "$*" >&2; exit 1; }

command -v curl >/dev/null 2>&1 || die "curl est requis."
command -v jq   >/dev/null 2>&1 || die "jq est requis."

# 1. Attendre que l'instance accepte un login (readiness réelle, pas heartbeat).
#    heartbeat répond 200 dès le démarrage du serveur HTTP, AVANT les migrations
#    et la création de l'admin — le login est le seul signal fiable.
log "Attente de l'instance Umami sur $BASE (login admin)…"
TOKEN=""
for i in $(seq 1 40); do
  resp="$(curl -s -X POST "$BASE/api/auth/login" \
    -H 'Content-Type: application/json' \
    -d "{\"username\":\"$USERNAME\",\"password\":\"$PASSWORD\"}" 2>/dev/null || true)"
  TOKEN="$(printf '%s' "$resp" | jq -r '.token // empty' 2>/dev/null || true)"
  if [ -n "$TOKEN" ]; then
    log "Login OK après ~$((i * 3))s."
    break
  fi
  sleep 3
done
[ -n "$TOKEN" ] || die "Login impossible après 120s (instance pas prête ou identifiants invalides)."

AUTH=(-H "Authorization: Bearer $TOKEN")

# 2. Idempotence : réutiliser le website de test s'il existe déjà.
log "Recherche du website « $WEBSITE_NAME »…"
WEBSITE_ID="$(curl -s "$BASE/api/websites?pageSize=200" "${AUTH[@]}" \
  | jq -r --arg n "$WEBSITE_NAME" '.data[]? | select(.name == $n) | .id' | head -n1)"

if [ -n "$WEBSITE_ID" ]; then
  log "Website existant réutilisé : $WEBSITE_ID"
else
  log "Création du website « $WEBSITE_NAME » (domain $HOSTNAME_)…"
  create_resp="$(curl -s -X POST "$BASE/api/websites" "${AUTH[@]}" \
    -H 'Content-Type: application/json' \
    -d "{\"name\":\"$WEBSITE_NAME\",\"domain\":\"$HOSTNAME_\"}")"
  WEBSITE_ID="$(printf '%s' "$create_resp" | jq -r '.id // empty')"
  [ -n "$WEBSITE_ID" ] || die "Création du website échouée : $create_resp"
  log "Website créé : $WEBSITE_ID"
fi

# 3. (Ré)écrire .env.test de façon atomique.
tmp="$(mktemp)"
cat > "$tmp" <<EOF
###> umami-test ###
# Généré par scripts/seed-umami.sh — ne pas éditer à la main (hors git).
UMAMI_TEST_BASE=$BASE
UMAMI_TEST_WEBSITE_ID=$WEBSITE_ID
UMAMI_TEST_HOSTNAME=$HOSTNAME_
UMAMI_TEST_USERNAME=$USERNAME
UMAMI_TEST_PASSWORD=$PASSWORD
###< umami-test ###
EOF
mv "$tmp" "$ENV_FILE"

log "Écrit $ENV_FILE"
log "Seed terminé. Website de test : $WEBSITE_ID"
