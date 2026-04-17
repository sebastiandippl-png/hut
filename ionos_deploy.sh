#!/bin/bash
# IONOS Deployment Script for hut app
# Uses values from ionos_deploy.local.conf.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_CONFIG_PATH="$SCRIPT_DIR/ionos_deploy.local.conf"
ENV_FILE="$SCRIPT_DIR/.env"

if [ ! -f "$DEPLOY_CONFIG_PATH" ]; then
    echo "Error: missing deployment config at $DEPLOY_CONFIG_PATH"
    exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
    echo "Error: missing .env file at $ENV_FILE"
    exit 1
fi

# shellcheck disable=SC1090
source "$DEPLOY_CONFIG_PATH"

IONOS_PASSWORD="${IONOS_PASSWORD:-}"
REMOTE_PATH="${REMOTE_PATH:-}"
REMOTE_PATH="${REMOTE_PATH%/}"
DRY_RUN="${DRY_RUN:-0}"
BUILD_RELEASE="${BUILD_RELEASE:-1}"
INCLUDE_VENDOR="${INCLUDE_VENDOR:-1}"
BUILD_VENDOR="${BUILD_VENDOR:-1}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${YELLOW}=== IONOS hut Deployment ===${NC}\n"

if [ "$#" -gt 0 ]; then
    echo -e "${RED}Error: unexpected arguments: $*${NC}"
    echo "Use env vars instead, for example: DRY_RUN=1 ./ionos_deploy.sh"
    exit 1
fi

if [ -z "${IONOS_HOST:-}" ] || [ -z "${IONOS_USER:-}" ]; then
    echo -e "${RED}Error: IONOS_HOST and IONOS_USER are required${NC}"
    exit 1
fi

if [ -z "$REMOTE_PATH" ] || [ "$REMOTE_PATH" = "/" ] || [ "$REMOTE_PATH" = "." ]; then
    echo -e "${RED}Error: REMOTE_PATH must be set in ionos_deploy.local.conf and must not be empty, '/' or '.'${NC}"
    exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
    echo -e "${RED}Error: rsync is not installed.${NC}"
    exit 1
fi

if ! command -v ssh >/dev/null 2>&1; then
    echo -e "${RED}Error: ssh is not installed.${NC}"
    exit 1
fi

USE_SSHPASS=0
if [ -n "$IONOS_PASSWORD" ]; then
    if command -v sshpass >/dev/null 2>&1; then
        USE_SSHPASS=1
        echo -e "${GREEN}Using password authentication via sshpass${NC}"
    else
        echo -e "${YELLOW}IONOS_PASSWORD set but sshpass is not installed; falling back to SSH key auth${NC}"
    fi
fi

ENV_BACKUP_FILE="$(mktemp)"
cp "$ENV_FILE" "$ENV_BACKUP_FILE"
ENV_MODIFIED=0
RELEASE_DIR=""

cleanup() {
    if [ "$ENV_MODIFIED" = "1" ] && [ -f "$ENV_BACKUP_FILE" ]; then
        cp "$ENV_BACKUP_FILE" "$ENV_FILE"
        echo -e "${GREEN}Restored APP_ENV to development in .env${NC}"
    fi
    rm -f "$ENV_BACKUP_FILE"

    if [ -n "$RELEASE_DIR" ] && [ -d "$RELEASE_DIR" ]; then
        rm -rf "$RELEASE_DIR"
    fi
}
trap cleanup EXIT

set_env_value() {
    local file="$1"
    local key="$2"
    local value="$3"
    local tmp
    tmp="$(mktemp)"

    awk -v key="$key" -v value="$value" '
        BEGIN { replaced = 0 }
        {
            if (!replaced && $0 ~ "^" key "=") {
                print key "=" value
                replaced = 1
            } else {
                print $0
            }
        }
        END {
            if (!replaced) {
                print key "=" value
            }
        }
    ' "$file" > "$tmp"

    mv "$tmp" "$file"
}

run_ssh() {
    local remote_cmd="$1"
    if [ "$USE_SSHPASS" = "1" ]; then
        sshpass -p "$IONOS_PASSWORD" ssh -o StrictHostKeyChecking=accept-new "$IONOS_USER@$IONOS_HOST" "$remote_cmd"
    else
        ssh -o StrictHostKeyChecking=accept-new "$IONOS_USER@$IONOS_HOST" "$remote_cmd"
    fi
}

run_rsync() {
    local src="$1"
    local dest="$2"
    shift 2
    local extra_args=("$@")

    if [ "$USE_SSHPASS" = "1" ]; then
        sshpass -p "$IONOS_PASSWORD" rsync "${extra_args[@]}" -e "ssh -o StrictHostKeyChecking=accept-new" "$src" "$dest"
    else
        rsync "${extra_args[@]}" -e "ssh -o StrictHostKeyChecking=accept-new" "$src" "$dest"
    fi
}

echo -e "${GREEN}Validating SSH connection...${NC}"
run_ssh "echo 'SSH connection OK'" >/dev/null

echo -e "${GREEN}Setting APP_ENV=production and MariaDB config in .env for deployment...${NC}"
set_env_value "$ENV_FILE" "APP_ENV" "production"
if [ -n "${DB_DSN:-}" ];      then set_env_value "$ENV_FILE" "DB_DSN"      "$DB_DSN";      fi
if [ -n "${DB_USERNAME:-}" ]; then set_env_value "$ENV_FILE" "DB_USERNAME" "$DB_USERNAME"; fi
if [ -n "${DB_PASSWORD:-}" ]; then set_env_value "$ENV_FILE" "DB_PASSWORD" "$DB_PASSWORD"; fi
if [ -n "${GOOGLE_REDIRECT_URI:-}" ]; then set_env_value "$ENV_FILE" "GOOGLE_REDIRECT_URI" "$GOOGLE_REDIRECT_URI"; fi
if [ -n "${ALWAYS_ADMIN_EMAILS:-}" ]; then set_env_value "$ENV_FILE" "ALWAYS_ADMIN_EMAILS" "$ALWAYS_ADMIN_EMAILS"; fi
ENV_MODIFIED=1

SOURCE_DIR="$SCRIPT_DIR"
if [ "$BUILD_RELEASE" = "1" ]; then
    RELEASE_DIR="$(mktemp -d)"
    SOURCE_DIR="$RELEASE_DIR"

    echo -e "${GREEN}Building release package in $RELEASE_DIR...${NC}"

    cp "$SCRIPT_DIR/composer.json" "$RELEASE_DIR/"
    cp "$SCRIPT_DIR/composer.lock" "$RELEASE_DIR/"
    cp "$SCRIPT_DIR/.env" "$RELEASE_DIR/"
    [ -f "$SCRIPT_DIR/.htaccess" ] && cp "$SCRIPT_DIR/.htaccess" "$RELEASE_DIR/"

    run_rsync "$SCRIPT_DIR/public/" "$RELEASE_DIR/public/" -a
    run_rsync "$SCRIPT_DIR/src/" "$RELEASE_DIR/src/" -a
    run_rsync "$SCRIPT_DIR/templates/" "$RELEASE_DIR/templates/" -a
    run_rsync "$SCRIPT_DIR/config/" "$RELEASE_DIR/config/" -a
    run_rsync "$SCRIPT_DIR/migrations/" "$RELEASE_DIR/migrations/" -a

    mkdir -p "$RELEASE_DIR/storage"
    if [ -d "$SCRIPT_DIR/storage" ]; then
        run_rsync "$SCRIPT_DIR/storage/" "$RELEASE_DIR/storage/" -a \
            --exclude='*.sqlite' \
            --exclude='*.sqlite-*' \
            --exclude='*.db' \
            --exclude='*.db-*'
    fi

    if [ "$INCLUDE_VENDOR" = "1" ]; then
        if [ "$BUILD_VENDOR" = "1" ]; then
            if ! command -v composer >/dev/null 2>&1; then
                echo -e "${RED}Error: composer is required for BUILD_VENDOR=1${NC}"
                exit 1
            fi
            echo -e "${GREEN}Installing production vendor dependencies for release...${NC}"
            (
                cd "$RELEASE_DIR"
                composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
            )
        else
            if [ ! -d "$SCRIPT_DIR/vendor" ]; then
                echo -e "${RED}Error: vendor directory not found and BUILD_VENDOR=0${NC}"
                exit 1
            fi
            echo -e "${GREEN}Copying existing vendor directory into release...${NC}"
            run_rsync "$SCRIPT_DIR/vendor/" "$RELEASE_DIR/vendor/" -a
        fi
    else
        echo -e "${YELLOW}INCLUDE_VENDOR=0: vendor will not be uploaded (remote must provide dependencies).${NC}"
    fi
fi

RSYNC_OPTS=(
    -avz
    --checksum
    --itemize-changes
    --delete
    --exclude='.git/'
    --exclude='.github/'
    --exclude='node_modules/'
    --exclude='bgg_dump/'
    --exclude='public/cache/'
    --exclude='storage/*.sqlite'
    --exclude='storage/*.sqlite-*'
    --exclude='storage/*.db'
    --exclude='storage/*.db-*'
    --exclude='ionos_deploy.sh'
    --exclude='ionos_deploy.local.conf'
    --exclude='*.swp'
    --exclude='.DS_Store'
)

if [ "$DRY_RUN" = "1" ]; then
    RSYNC_OPTS+=(--dry-run)
    echo -e "${YELLOW}DRY RUN enabled: no files will be changed${NC}"
fi

echo -e "${GREEN}Remote target: $IONOS_USER@$IONOS_HOST:$REMOTE_PATH/${NC}"
echo -e "${GREEN}Deleting remote deployment at $REMOTE_PATH while keeping public/cache and vendor...${NC}"
run_ssh "mkdir -p '$REMOTE_PATH/public/cache' '$REMOTE_PATH/public/cache/thumbnails'"
if [ "$DRY_RUN" = "1" ]; then
    run_ssh "find '$REMOTE_PATH' -mindepth 1 \
        ! -path '$REMOTE_PATH/public/cache' ! -path '$REMOTE_PATH/public/cache/*' \
        ! -path '$REMOTE_PATH/vendor'        ! -path '$REMOTE_PATH/vendor/*' \
        -print"
else
    run_ssh "find '$REMOTE_PATH' -mindepth 1 \
        ! -path '$REMOTE_PATH/public/cache' ! -path '$REMOTE_PATH/public/cache/*' \
        ! -path '$REMOTE_PATH/vendor'        ! -path '$REMOTE_PATH/vendor/*' \
        -exec rm -rf {} +"
fi

# Upload app files with --delete but leave vendor dir to the incremental pass below
RSYNC_APP_OPTS=("${RSYNC_OPTS[@]}" --exclude='vendor/')

echo -e "${GREEN}Uploading application files to $IONOS_USER@$IONOS_HOST:$REMOTE_PATH/...${NC}"
run_rsync "$SOURCE_DIR/" "$IONOS_USER@$IONOS_HOST:$REMOTE_PATH/" "${RSYNC_APP_OPTS[@]}"

# Sync vendor incrementally: no --delete so existing server files are kept and only
# new or changed files are transferred.
if [ "$INCLUDE_VENDOR" = "1" ] && [ -d "$SOURCE_DIR/vendor" ]; then
    RSYNC_VENDOR_OPTS=(
        -avz
        --checksum
        --itemize-changes
        --exclude='.DS_Store'
        --exclude='*.swp'
    )
    if [ "$DRY_RUN" = "1" ]; then
        RSYNC_VENDOR_OPTS+=(--dry-run)
    fi
    echo -e "${GREEN}Syncing vendor directory incrementally (no delete)...${NC}"
    run_rsync "$SOURCE_DIR/vendor/" "$IONOS_USER@$IONOS_HOST:$REMOTE_PATH/vendor/" "${RSYNC_VENDOR_OPTS[@]}"
fi

echo -e "${GREEN}Setting file permissions on server...${NC}"
if [ "$DRY_RUN" != "1" ]; then
    run_ssh "find '$REMOTE_PATH' -type d -exec chmod 755 {} + && find '$REMOTE_PATH' -type f -exec chmod 644 {} + && chmod 600 '$REMOTE_PATH/.env'"
fi

echo -e "${GREEN}Deployment upload finished successfully.${NC}"

