#!/usr/bin/env bash
set -euo pipefail

required_paths=(
  "AGENTS.md"
  "README.md"
  "docker-compose.yml"
  "docs/product-requirements.md"
  "docs/database-schema.md"
  "docs/api-contracts.md"
  "frontend/package.json"
  "backend/composer.json"
  "shared/types/api.ts"
)

for path in "${required_paths[@]}"; do
  if [[ ! -e "$path" ]]; then
    echo "Missing required path: $path" >&2
    exit 1
  fi
done

echo "Project scaffold check passed."
