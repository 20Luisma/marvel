#!/usr/bin/env bash
set -euo pipefail

# Ejecuta las comprobaciones desde la raíz del repo para evitar rutas relativas frágiles.
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

echo ">> [1/2] Ejecutando auditoría de vulnerabilidades (composer audit)..."
composer audit --no-interaction

echo ">> [2/2] Lint de sintaxis PHP (php -l en src/ y tests/)..."
find src tests -name '*.php' -print0 | xargs -0 -r -n1 -P4 php -l

echo "Security Check completado correctamente."
