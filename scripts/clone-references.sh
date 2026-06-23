#!/bin/bash
set -euo pipefail
mkdir -p reference

# ⚠ Vérifier le tag exact : `git ls-remote --tags https://github.com/umami-software/umami | grep 3.1.0`
# Si le nom diffère de v3.1.0, prendre le tag réel et le noter dans docs/ENVIRONMENT.md.
[ -d reference/umami ] || git clone --depth 1 --branch v3.1.0 \
  https://github.com/umami-software/umami reference/umami

# Optionnel : source Saloon en arbitre local (sinon Context7 en première intention).
# [ -d reference/saloon ] || git clone --depth 1 https://github.com/saloonphp/saloon reference/saloon
