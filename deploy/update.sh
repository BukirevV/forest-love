#!/usr/bin/env bash
#
# Обновление «Лес Любви» на сервере из GitHub.
#
#   sudo ./deploy/update.sh                 — обновить, если на GitHub есть новые коммиты
#   sudo ./deploy/update.sh --check         — только показать, есть ли обновления
#   sudo ./deploy/update.sh --force         — выполнить все шаги, даже если новых коммитов нет
#   sudo ./deploy/update.sh --branch=dev    — обновиться с другой ветки (по умолчанию master)
#   sudo ./deploy/update.sh --no-backup     — не делать резервную копию базы
#
# Что делает:
#   1. проверяет, что на сервере нет ручных правок в файлах проекта;
#   2. забирает ветку с GitHub и показывает новые коммиты;
#   3. делает резервную копию базы (pg_dump) в $BACKUP_DIR, хранит последние $BACKUP_KEEP;
#   4. обновляет код (git reset --hard origin/<ветка>), .env, загрузки и runtime не трогаются;
#   5. composer install (если менялись composer.json/lock или нет vendor);
#   6. миграции, RBAC, сброс кэша, права на папки;
#   7. перезапускает обработчик очереди и php-fpm;
#   8. при ошибке на шагах 4–6 возвращает код к предыдущей версии.
#
# Настройки можно переопределить переменными окружения или в файле deploy/update.conf
# (он не в git, если добавлен в .gitignore), например:
#   BRANCH=master
#   PHP_BIN=/usr/bin/php8.4
#   WEB_USER=www-data
#   BACKUP_DIR=/var/backups/forest-love
#
# Весь код — внутри main(): bash читает функцию целиком до запуска, поэтому обновление
# самого этого файла через git во время работы ничего не ломает.

set -Eeuo pipefail

main() {
    local SCRIPT_DIR APP_DIR
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    APP_DIR="${APP_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"

    # shellcheck disable=SC1091
    [[ -f "$SCRIPT_DIR/update.conf" ]] && source "$SCRIPT_DIR/update.conf"

    local BRANCH="${BRANCH:-master}"
    local REMOTE="${REMOTE:-origin}"
    local PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
    local COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
    local WEB_USER="${WEB_USER:-www-data}"
    local QUEUE_SERVICE="${QUEUE_SERVICE:-queue-worker}"
    local BACKUP_DIR="${BACKUP_DIR:-/var/backups/forest-love}"
    local BACKUP_KEEP="${BACKUP_KEEP:-10}"
    local LOG_FILE="${LOG_FILE:-/var/log/forest-love-update.log}"

    local CHECK_ONLY=0 FORCE=0 BACKUP=1
    for arg in "$@"; do
        case "$arg" in
            --check) CHECK_ONLY=1 ;;
            --force) FORCE=1 ;;
            --no-backup) BACKUP=0 ;;
            --branch=*) BRANCH="${arg#*=}" ;;
            -h|--help) sed -n '2,30p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; return 0 ;;
            *) echo "Неизвестный параметр: $arg (см. --help)" >&2; return 2 ;;
        esac
    done

    # --- окружение -----------------------------------------------------------------
    if [[ $EUID -ne 0 ]]; then
        echo "Запустите от root: sudo $0 $*" >&2
        return 1
    fi
    [[ -d "$APP_DIR/.git" ]] || { echo "В $APP_DIR нет git-репозитория." >&2; return 1; }
    [[ -n "$PHP_BIN" && -x "$PHP_BIN" ]] || { echo "PHP не найден, укажите PHP_BIN=/usr/bin/php8.4" >&2; return 1; }

    # Владелец файлов проекта: от его имени работают git и composer
    local APP_USER
    APP_USER="$(stat -c %U "$APP_DIR")"

    exec 9>"/tmp/forest-love-update.lock"
    if ! flock -n 9; then
        echo "Обновление уже выполняется другим процессом." >&2
        return 1
    fi

    if [[ $CHECK_ONLY -eq 0 ]]; then
        mkdir -p "$(dirname "$LOG_FILE")"
        exec > >(tee -a "$LOG_FILE") 2>&1
    fi

    cd "$APP_DIR"
    log "=== Обновление $APP_DIR, ветка $BRANCH (файлы: $APP_USER, веб-сервер: $WEB_USER)"

    # --- 1. ручные правки на сервере ---------------------------------------------------
    local dirty
    # права на файлы (chmod) правкой не считаются
    dirty="$(as_app git -c core.fileMode=false status --porcelain --untracked-files=no)"
    if [[ -n "$dirty" ]]; then
        log "ОСТАНОВЛЕНО: на сервере изменены файлы проекта, обновление их перезапишет:"
        echo "$dirty"
        log "Перенесите правки в репозиторий или отмените их: git checkout -- <файл> (или git stash)."
        return 1
    fi

    # --- 2. новые коммиты ------------------------------------------------------------
    as_app git fetch --prune "$REMOTE" "$BRANCH"
    local OLD_REV NEW_REV
    OLD_REV="$(as_app git rev-parse HEAD)"
    NEW_REV="$(as_app git rev-parse "$REMOTE/$BRANCH")"

    if [[ "$OLD_REV" == "$NEW_REV" && $FORCE -eq 0 ]]; then
        log "Обновлений нет: версия $(short "$OLD_REV") актуальна."
        return 0
    fi
    if [[ "$OLD_REV" != "$NEW_REV" ]]; then
        log "Новые коммиты ($(short "$OLD_REV") → $(short "$NEW_REV")):"
        as_app git log --oneline --no-decorate "$OLD_REV..$NEW_REV" | sed 's/^/    /' || true
    fi
    if [[ $CHECK_ONLY -eq 1 ]]; then
        return 0
    fi

    local CHANGED
    CHANGED="$(as_app git diff --name-only "$OLD_REV" "$NEW_REV" || true)"

    # --- 3. резервная копия базы -------------------------------------------------------
    if [[ $BACKUP -eq 1 ]]; then
        backup_db
    fi

    # --- 4–6. обновление с откатом при ошибке --------------------------------------------
    ROLLBACK_REV="$OLD_REV"
    trap 'on_error $LINENO' ERR

    log "Обновление кода"
    local CURRENT_BRANCH
    CURRENT_BRANCH="$(as_app git rev-parse --abbrev-ref HEAD)"
    if [[ "$CURRENT_BRANCH" != "$BRANCH" ]]; then
        as_app git checkout -B "$BRANCH" "$REMOTE/$BRANCH"
    fi
    as_app git reset --hard "$REMOTE/$BRANCH"

    if [[ ! -f vendor/autoload.php || $FORCE -eq 1 ]] || grep -qE '^composer\.(json|lock)$' <<<"$CHANGED"; then
        COMPOSER_RAN=1
        composer_install
    else
        log "Зависимости не менялись — composer пропущен"
    fi

    log "Миграции"
    yii migrate --interactive=0
    log "RBAC"
    yii rbac/init
    log "Сброс кэша"
    yii cache/flush-all --interactive=0 || log "  (кэш не сброшен — не критично)"

    fix_permissions

    trap - ERR
    ROLLBACK_REV=""

    # --- 7. перезапуск сервисов -------------------------------------------------------
    restart_services

    log "=== Готово: версия $(short "$NEW_REV") ($(as_app git log -1 --format='%s' | cut -c1-80))"
}

# ------------------------------------------------------------------------------------------
log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
short() { echo "${1:0:7}"; }

as_app() {
    if [[ "$APP_USER" == "root" ]]; then
        "$@"
    else
        sudo -u "$APP_USER" -H "$@"
    fi
}

yii() {
    as_app "$PHP_BIN" "$APP_DIR/yii" "$@"
}

composer_install() {
    [[ -n "$COMPOSER_BIN" ]] || { log "composer не найден, укажите COMPOSER_BIN"; return 1; }
    # Если на сервере стоят dev-пакеты (Gii, Debug) — сохраняем как есть,
    # иначе ставим без них. Переопределить: COMPOSER_FLAGS="--no-dev"
    local flags="${COMPOSER_FLAGS:-}"
    if [[ -z "$flags" && ! -d vendor/yiisoft/yii2-gii ]]; then
        flags="--no-dev"
    fi
    log "composer install $flags"
    as_app env COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" "$COMPOSER_BIN" install \
        --no-interaction --prefer-dist --optimize-autoloader $flags
}

backup_db() {
    local env_file="$APP_DIR/.env"
    if ! command -v pg_dump >/dev/null; then
        log "pg_dump не найден — резервная копия базы пропущена"
        return 0
    fi
    local dsn user pass host port db
    dsn="$(env_value DB_DSN "$env_file")"
    user="$(env_value DB_USER "$env_file")"
    pass="$(env_value DB_PASSWORD "$env_file")"
    if [[ "$dsn" != pgsql:* ]]; then
        log "База не PostgreSQL ($dsn) — резервная копия пропущена"
        return 0
    fi
    host="$(dsn_part host "$dsn")"; port="$(dsn_part port "$dsn")"; db="$(dsn_part dbname "$dsn")"
    mkdir -p "$BACKUP_DIR"
    chmod 700 "$BACKUP_DIR"
    local file="$BACKUP_DIR/${db}_$(date +%Y%m%d_%H%M%S)_$(short "$OLD_REV").sql.gz"
    log "Резервная копия базы → $file"
    PGPASSWORD="$pass" pg_dump -h "${host:-127.0.0.1}" -p "${port:-5432}" -U "$user" --no-owner "$db" | gzip > "$file"
    chmod 600 "$file"
    # шаблон имени зависит от названия базы — удаляем только свои старые копии
    ls -1t "$BACKUP_DIR/${db}_"*.sql.gz 2>/dev/null | tail -n +$((BACKUP_KEEP + 1)) | xargs -r rm -f
}

env_value() {
    # значение переменной из .env без кавычек
    local line
    line="$(grep -E "^[[:space:]]*$1[[:space:]]*=" "$2" | tail -n1 | cut -d= -f2-)" || true
    line="${line%$'\r'}"
    line="${line#"${line%%[![:space:]]*}"}"
    line="${line%"${line##*[![:space:]]}"}"
    [[ "$line" == \"*\" || "$line" == \'*\' ]] && line="${line:1:-1}"
    echo "$line"
}

dsn_part() {
    sed -n "s/.*[:;]$1=\([^;]*\).*/\1/p" <<<"$2"
}

fix_permissions() {
    local dir
    for dir in runtime web/assets web/uploads storage import; do
        if [[ -d "$APP_DIR/$dir" ]]; then
            chown -R "$WEB_USER:$WEB_USER" "$APP_DIR/$dir"
        fi
    done
}

restart_services() {
    if ! command -v systemctl >/dev/null; then
        log "systemctl нет — перезапустите обработчик очереди и PHP вручную"
        return 0
    fi
    if systemctl list-unit-files "$QUEUE_SERVICE.service" --no-legend 2>/dev/null | grep -q .; then
        log "Перезапуск $QUEUE_SERVICE"
        systemctl restart "$QUEUE_SERVICE"
    else
        log "Служба $QUEUE_SERVICE не установлена — очередь не перезапущена (см. deploy/queue-worker.service)"
    fi
    # php-fpm держит старый код в OPcache — перечитываем
    local fpm
    for fpm in $(systemctl list-units --type=service --state=running --no-legend 'php*-fpm*' 2>/dev/null | awk '{print $1}'); do
        log "Перезагрузка $fpm"
        systemctl reload "$fpm" || systemctl restart "$fpm"
    done
}

on_error() {
    local line="$1"
    trap - ERR
    log "ОШИБКА на строке $line."
    if [[ -n "${ROLLBACK_REV:-}" ]]; then
        log "Откат кода к $(short "$ROLLBACK_REV")…"
        as_app git reset --hard "$ROLLBACK_REV" || true
        if [[ "${COMPOSER_RAN:-0}" -eq 1 ]]; then
            composer_install || log "composer при откате завершился с ошибкой — проверьте vendor"
        fi
        fix_permissions || true
        restart_services || true
        log "Код возвращён к предыдущей версии. Если миграции успели примениться, база могла измениться —"
        log "резервная копия: $BACKUP_DIR (восстановление: gunzip -c файл.sql.gz | psql -U <user> <db>)."
    fi
    exit 1
}

ROLLBACK_REV=""
APP_USER=""
COMPOSER_RAN=0
main "$@"; exit $?
