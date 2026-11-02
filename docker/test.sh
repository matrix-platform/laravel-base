#!/usr/bin/env bash
set -e

cd "$(dirname "$0")/.."
docker compose exec php vendor/bin/phpunit "$@"
