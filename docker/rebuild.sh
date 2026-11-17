#!/usr/bin/env bash
set -e

cd "$(dirname "$0")/.."
docker compose build --pull php
docker compose pull postgres
docker compose up -d
