#!/usr/bin/env bash
# Единый деплой: staging (test.kidscrm.online) или production (kidscrm.online).
# Запуск из корня репозитория:
#   ./tests/deploy/deploy.sh test
#   ./tests/deploy/deploy.sh prod
#   ./tests/deploy/deploy.sh test --dry-run
#
# Требуются sudo там, где указано (chown/chmod/systemctl/service).

set -euo pipefail

TEST_ROOT="/home/prukon/web/test.kidscrm.online/public_html"
PROD_ROOT="/home/prukon/web/kidscrm.online/public_html"

DRY_RUN=0
MODE=""

usage() {
  echo "Использование: $0 {test|prod} [--dry-run]" >&2
  exit 2
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    test|prod) MODE="$1" ;;
    --dry-run) DRY_RUN=1 ;;
    -h|--help) usage ;;
    *) echo "Неизвестный аргумент: $1" >&2; usage ;;
  esac
  shift
done

[[ -n "$MODE" ]] || usage

ROOT=""
if [[ "$MODE" == "test" ]]; then
  ROOT="$TEST_ROOT"
else
  ROOT="$PROD_ROOT"
fi

BLOG_DIR="${ROOT}/storage/app/public/blog"
TBANK_LOG="${ROOT}/storage/logs/tbank"
LOCKFILE="/tmp/kidscrm-deploy-${MODE}.lock"

run() {
  if (( DRY_RUN )); then
    printf 'DRY-RUN:'
    printf ' %q' "$@"
    printf '\n'
  else
    "$@"
  fi
}

# Читает значение ключа из .env (первая подходящая строка), без подстановки shell.
read_env() {
  local file="$1" key="$2" line val
  [[ -f "$file" ]] || return 1
  line=$(grep -E "^[[:space:]]*${key}[[:space:]]*=" "$file" | head -n1) || return 1
  val="${line#*=}"
  val="${val#"${val%%[![:space:]]*}"}"  # trim leading
  val="${val%"${val##*[![:space:]]}"}"  # trim trailing
  val="${val%$'\r'}"
  if [[ "$val" == \"*\" ]]; then val="${val#\"}"; val="${val%\"}"
  elif [[ "$val" == \'*\' ]]; then val="${val#\'}"; val="${val%\'}"
  fi
  printf '%s' "$val"
}

verify_staging_env() {
  local envfile="$1"
  local name env
  name="$(read_env "$envfile" APP_NAME)" || return 1
  env="$(read_env "$envfile" APP_ENV)" || return 1
  if [[ "$name" != "test.kidscrm.online" ]]; then
    echo "Ошибка: ожидался APP_NAME=test.kidscrm.online в ${envfile}, получено: ${name}" >&2
    return 1
  fi
  if [[ "$env" != "staging" ]]; then
    echo "Ошибка: ожидался APP_ENV=staging в ${envfile}, получено: ${env}" >&2
    return 1
  fi
}

verify_production_env() {
  local envfile="$1"
  local name env debug
  name="$(read_env "$envfile" APP_NAME)" || return 1
  env="$(read_env "$envfile" APP_ENV)" || return 1
  debug="$(read_env "$envfile" APP_DEBUG)" || return 1
  if [[ "$name" != "kidscrm.online" ]]; then
    echo "Ошибка: ожидался APP_NAME=kidscrm.online в ${envfile}, получено: ${name}" >&2
    return 1
  fi
  if [[ "$env" != "production" ]]; then
    echo "Ошибка: ожидался APP_ENV=production в ${envfile}, получено: ${env}" >&2
    return 1
  fi
  if [[ "${debug,,}" != "false" ]]; then
    echo "Ошибка: ожидался APP_DEBUG=false в ${envfile}, получено: ${debug}" >&2
    return 1
  fi
}

if [[ ! -d "$ROOT" ]]; then
  echo "Ошибка: каталог проекта не найден: ${ROOT}" >&2
  exit 1
fi

ENV_FILE="${ROOT}/.env"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Ошибка: нет файла ${ENV_FILE}" >&2
  exit 1
fi

if [[ "$MODE" == "test" ]]; then
  verify_staging_env "$ENV_FILE"
else
  verify_production_env "$ENV_FILE"
fi

exec 9>"$LOCKFILE"
if (( DRY_RUN )); then
  printf 'DRY-RUN: flock -n 9 (lock %q)\n' "$LOCKFILE"
else
  flock -n 9 || {
    echo "Ошибка: уже идёт другой деплой (${MODE}). Lock: ${LOCKFILE}" >&2
    exit 1
  }
fi

cd "$ROOT"

# Сначала vendor (composer), иначе artisan (в т.ч. down) падает без vendor/autoload.php.
if [[ "$MODE" == "test" ]]; then
  echo "==> [test] composer install (до maintenance)"
  run composer install --optimize-autoloader -n --no-scripts

  echo "==> [test] npm ci && npm run build (до maintenance)"
  run npm ci
  run npm run build
else
  echo "==> [prod] composer install (до maintenance)"
  run composer install --no-dev --optimize-autoloader -n --no-scripts

  echo "==> [prod] npm ci && build (до maintenance)"
  run npm ci
  run npm run build
fi

down_done=0
maintenance_off() {
  if (( down_done )); then
    run php artisan up || true
    down_done=0
  fi
}

trap maintenance_off EXIT INT TERM

if (( DRY_RUN )); then
  run php artisan down
  down_done=1
else
  php artisan down
  down_done=1
fi

if [[ "$MODE" == "test" ]]; then
  echo "==> [test] migrate"
  run php artisan migrate --force

  echo "==> [test] кеш приложения и config:cache"
  run php artisan cache:clear
  run php artisan optimize:clear
  run php artisan config:cache

  echo "==> [test] package:discover"
  run php artisan package:discover --ansi

  echo "==> [test] PermissionSeeder"
  run php artisan db:seed --class='Database\Seeders\PermissionSeeder' --force

  echo "==> [test] chown проекта (sudo)"
  run sudo chown -R prukon:www-data "${ROOT}"

  if [[ -d "${BLOG_DIR}" ]]; then
    echo "==> [test] права на storage/app/public/blog"
    run sudo chown -R prukon:www-data "${BLOG_DIR}"
    run sudo find "${BLOG_DIR}" -type d -exec chmod 2775 {} \;
    run sudo find "${BLOG_DIR}" -type f -exec chmod 664 {} \;
  fi

  echo "==> [test] очередь"
  run sudo systemctl restart kidscrm-queue.service

  if [[ -e "${TBANK_LOG}" ]]; then
    echo "==> [test] tbank log"
    run sudo chown prukon "${TBANK_LOG}"
    run sudo chmod 775 "${TBANK_LOG}"
  fi

  echo "==> [test] PHP-FPM 8.2"
  run sudo systemctl restart php8.2-fpm.service
else
  echo "==> [prod] chown (sudo)"
  run sudo chown -R prukon:www-data "${ROOT}"

  echo "==> [prod] migrate"
  run php artisan migrate --force

  echo "==> [prod] кеш и config:cache"
  run php artisan cache:clear
  run php artisan optimize:clear
  run php artisan config:cache

  echo "==> [prod] package:discover"
  run php artisan package:discover --ansi

  echo "==> [prod] DatabaseSeeder"
  run php artisan db:seed --class='Database\Seeders\DatabaseSeeder' --force

  echo "==> [prod] queue:restart"
  run php artisan queue:restart

  if [[ -e "${TBANK_LOG}" ]]; then
    echo "==> [prod] tbank log"
    run sudo chown prukon "${TBANK_LOG}"
    run sudo chmod 775 "${TBANK_LOG}"
  fi

  run sudo systemctl restart kidscrm-queue.service
  run sudo systemctl restart php8.2-fpm.service
fi

maintenance_off
trap - EXIT INT TERM

echo "deploy (${MODE}): готово."
