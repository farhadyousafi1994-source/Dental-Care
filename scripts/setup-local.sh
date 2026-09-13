#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for tool in php composer node npm; do command -v "$tool" >/dev/null || { echo "$tool is missing from PATH."; exit 1; }; done
if [[ ! -f backend/.env ]]; then
  cp backend/.env.example backend/.env
  echo 'Created backend/.env. Set MySQL credentials and initial CMS administrator variables.'
  read -r -p 'Press Enter after editing .env, or type cancel: ' answer
  [[ "$answer" != cancel ]] || exit 0
fi
(
  cd backend
  composer install --no-interaction
  if ! grep -Eq '^APP_KEY=.+$' .env; then php artisan key:generate; fi
  php artisan config:clear
  read -r -p 'Apply migrations and seed defaults to the configured LOCAL database? Type MIGRATE: ' answer
  [[ "$answer" == MIGRATE ]] || { echo 'Database changes skipped.'; exit 2; }
  php artisan migrate --seed
  [[ -e public/storage ]] || php artisan storage:link
  php artisan cms:doctor
)
(cd frontend && npm ci && npm run build)
echo 'Setup complete. Start Laravel and Vue in separate terminals (docs/LOCAL_SETUP.md).'
