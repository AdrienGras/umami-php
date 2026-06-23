#!/bin/bash
#
# Porte de validation (CLAUDE.md règle d'or 8) — TOUT doit passer avant chaque commit.
# Usage : bash scripts/check.sh
#
# Inclut : composer validate, composer audit (zéro CVE), php-cs-fixer (dry-run),
#          phpstan, phpunit --testsuite unit.
# N'inclut PAS les tests d'intégration (docker requis) — à lancer séparément.
#
set -uo pipefail

fail=0

run() {
    local name="$1"
    shift
    echo "──▶ ${name}"
    if "$@"; then
        echo "   ✅ ${name}"
    else
        echo "   ❌ ${name}"
        fail=1
    fi
    echo
}

run "composer validate" composer validate --no-check-publish
run "composer audit"    composer audit
run "php-cs-fixer"      vendor/bin/php-cs-fixer fix --dry-run --diff
run "phpunit (unit)"    vendor/bin/phpunit --testsuite unit

# phpstan : tant que src/ est vide (avant l'étape 5), il renvoie « No files found to
# analyse » avec un code non-zéro — ce n'est pas un échec réel, on le traite en « skip ».
echo "──▶ phpstan"
phpstan_out=$(vendor/bin/phpstan analyse --no-progress 2>&1)
phpstan_code=$?
if [ "${phpstan_code}" -eq 0 ]; then
    echo "   ✅ phpstan"
elif echo "${phpstan_out}" | grep -q "No files found to analyse"; then
    echo "   ⏭  phpstan — aucun fichier à analyser (src/ vide, normal avant l'étape 5)"
else
    echo "${phpstan_out}"
    echo "   ❌ phpstan"
    fail=1
fi
echo

if [ "${fail}" -ne 0 ]; then
    echo "❌ Validation échouée — NE PAS committer. Corrige les points en rouge d'abord."
    exit 1
fi

echo "✅ Tout est vert — commit autorisé."
