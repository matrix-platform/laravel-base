#!/usr/bin/env bash
set -e

[ -f .env ] || cp .env.example .env

chmod 777 bootstrap/cache storage/app/private storage/app/public storage/framework/views storage/logs
