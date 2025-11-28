#!/usr/bin/env bash
set -euo pipefail

# chmod +x bin/security-check.sh

echo ">> Running security PHPUnit suite..."
vendor/bin/phpunit --colors=always tests/Security

echo ">> Running PHPStan on security namespaces..."
PHPSTAN_DISABLE_PARALLEL=1 vendor/bin/phpstan analyse --memory-limit=1G src/Security tests/Security

echo ">> Running composer audit..."
composer audit --no-interaction
