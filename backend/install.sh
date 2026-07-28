#!/usr/bin/env bash
set -euo pipefail
umask 027

VERSION=1.4.0
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
WEB_ROOT=${C2C_PUBLIC_WEB_ROOT:-/var/www/html/uniloud-click2call}
WEB_USER=${C2C_PUBLIC_WEB_USER:-}
PHP_BIN=${C2C_PUBLIC_PHP_BIN:-}
REPLACE_CODE=0

usage() {
  cat <<'EOF'
Usage: sudo ./install.sh [options]

  --php-bin /absolute/path   PHP 8.0+ CLI used for validation
  --web-root /absolute/path  Endpoint directory
  --web-user USER            Apache/Nginx/PHP-FPM user
  --replace-code             Back up and replace v1.4.0 code
  --help
EOF
}

while (($#)); do
  case "$1" in
    --php-bin) PHP_BIN=${2:-}; shift ;;
    --web-root) WEB_ROOT=${2:-}; shift ;;
    --web-user) WEB_USER=${2:-}; shift ;;
    --replace-code) REPLACE_CODE=1 ;;
    --help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
  esac
  shift
done

[[ $EUID -eq 0 ]] || { echo "Run as root." >&2; exit 1; }
[[ $WEB_ROOT == /* && $WEB_ROOT != / && $WEB_ROOT != /var/www/html ]] || {
  echo "Use a dedicated safe absolute web directory, not / or /var/www/html." >&2
  exit 1
}

php_supported() {
  local candidate=$1 version_id
  [[ $candidate == /* && -x $candidate ]] || return 1
  version_id=$("$candidate" -r 'echo PHP_VERSION_ID;' 2>/dev/null || true)
  [[ $version_id =~ ^[0-9]+$ ]] || return 1
  ((version_id >= 80000 && version_id < 90000))
}

if [[ -n $PHP_BIN ]]; then
  PHP_BIN=$(readlink -f -- "$PHP_BIN")
  php_supported "$PHP_BIN" || {
    echo "Selected --php-bin must be in the PHP 8 series." >&2
    exit 1
  }
else
  candidates=()
  command -v php >/dev/null 2>&1 && candidates+=("$(command -v php)")
  for candidate in /usr/bin/php8.* /usr/local/bin/php8.*; do
    [[ -x $candidate ]] && candidates+=("$candidate")
  done
  while IFS= read -r candidate; do
    candidate=$(readlink -f -- "$candidate")
    php_supported "$candidate" && PHP_BIN=$candidate
  done < <(printf '%s\n' "${candidates[@]}" | sort -uV)
  [[ -n $PHP_BIN ]] || {
    echo "No PHP 8.x CLI was found. Install PHP 8.2+ (8.0/8.1 compatibility is retained)." >&2
    exit 1
  }
fi
PHP_VERSION=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION, ".", PHP_RELEASE_VERSION;')

if [[ -z $WEB_USER ]]; then
  WEB_USER=$(
    ps -eo user=,comm= 2>/dev/null |
      awk '$1 != "root" && ($2 == "httpd" || $2 == "apache2" || $2 ~ /^php-fpm/) {print $1; exit}'
  )
fi
if [[ -z $WEB_USER ]]; then
  for candidate in asterisk apache www-data nginx; do
    if id "$candidate" >/dev/null 2>&1; then
      WEB_USER=$candidate
      break
    fi
  done
fi
[[ -n $WEB_USER ]] || { echo "Unable to determine web user; pass --web-user." >&2; exit 1; }
id "$WEB_USER" >/dev/null 2>&1 || { echo "Web user does not exist: $WEB_USER" >&2; exit 1; }
WEB_GROUP=$(id -gn "$WEB_USER")

CONFIG_DIR=/etc/uniloud-click2call-public
STATE_DIR=/var/lib/uniloud-click2call-public
LOG_DIR=/var/log/uniloud-click2call-public
RELEASE_ROOT=/opt/uniloud-click2call-public/releases
RELEASE_DIR=$RELEASE_ROOT/$VERSION
ACTIVE_LINK=/usr/local/lib/uniloud-click2call-public
BACKUP_ROOT=/var/backups/uniloud-click2call-public
ORIGINATE_PATH=$WEB_ROOT/originate_call.php
DIRECTORY_PATH=$WEB_ROOT/list_extensions.php

if [[ -e $RELEASE_DIR || -e $ORIGINATE_PATH || -e $DIRECTORY_PATH ]]; then
  [[ $REPLACE_CODE -eq 1 ]] || {
    echo "Click-to-Call public code already exists. Use upgrade.sh or --replace-code." >&2
    exit 1
  }
  stamp=$(date -u +%Y%m%dT%H%M%SZ)
  backup=$BACKUP_ROOT/install-$stamp
  install -d -m 0700 "$backup"
  for path in "$RELEASE_DIR" "$ORIGINATE_PATH" "$DIRECTORY_PATH"; do
    if [[ -e $path || -L $path ]]; then
      cp -a --parents -- "$path" "$backup"
    fi
  done
  find "$backup" -type f -exec sha256sum {} + >"$backup/SHA256SUMS"
fi

install -d -m 0755 -o root -g root "$RELEASE_ROOT"
stage=$(mktemp -d "$RELEASE_ROOT/.${VERSION}.XXXXXX")
cleanup() {
  [[ -z ${stage:-} || ! -d ${stage:-} ]] || rm -rf -- "$stage"
}
trap cleanup EXIT
cp -a "$SCRIPT_DIR/." "$stage/"
find "$stage" -type d -exec chmod 0755 {} +
find "$stage" -type f -exec chmod 0644 {} +
find "$stage" -type f \( -name '*.sh' -o -name '*.php' \) -path '*/tools/*' \
  -exec chmod 0755 {} +
chmod 0755 "$stage/install.sh" "$stage/upgrade.sh" \
  "$stage/rollback.sh" "$stage/uninstall.sh"

if [[ -e $RELEASE_DIR ]]; then
  [[ $RELEASE_DIR == "$RELEASE_ROOT/$VERSION" ]] || {
    echo "Unsafe release target." >&2
    exit 1
  }
  rm -rf -- "$RELEASE_DIR"
fi
mv "$stage" "$RELEASE_DIR"
stage=

if [[ -e $ACTIVE_LINK && ! -L $ACTIVE_LINK ]]; then
  echo "$ACTIVE_LINK exists and is not a symlink." >&2
  exit 1
fi
ln -sfn "$RELEASE_DIR" "$ACTIVE_LINK"

install -d -m 0755 -o root -g root "$WEB_ROOT"
install -m 0644 -o root -g root \
  "$RELEASE_DIR/public/originate_call.php" "$ORIGINATE_PATH"
install -m 0644 -o root -g root \
  "$RELEASE_DIR/public/list_extensions.php" "$DIRECTORY_PATH"

install -d -m 0750 -o root -g "$WEB_GROUP" "$CONFIG_DIR"
if [[ ! -f $CONFIG_DIR/config.php ]]; then
  install -m 0640 -o root -g "$WEB_GROUP" \
    "$RELEASE_DIR/config/config.php.example" "$CONFIG_DIR/config.php"
  echo "Created $CONFIG_DIR/config.php; replace every CHANGE_ME value."
else
  chown root:"$WEB_GROUP" "$CONFIG_DIR/config.php"
  chmod 0640 "$CONFIG_DIR/config.php"
fi

install -d -m 0750 -o "$WEB_USER" -g "$WEB_GROUP" "$STATE_DIR"
install -d -m 0750 -o "$WEB_USER" -g "$WEB_GROUP" "$STATE_DIR/rate"
install -d -m 0750 -o "$WEB_USER" -g "$WEB_GROUP" "$LOG_DIR"
if [[ ! -f $LOG_DIR/audit.log ]]; then
  install -m 0640 -o "$WEB_USER" -g "$WEB_GROUP" /dev/null "$LOG_DIR/audit.log"
else
  chown "$WEB_USER:$WEB_GROUP" "$LOG_DIR/audit.log"
  chmod 0640 "$LOG_DIR/audit.log"
fi

sed \
  -e "s/__WEB_USER__/$WEB_USER/g" \
  -e "s/__WEB_GROUP__/$WEB_GROUP/g" \
  "$RELEASE_DIR/logrotate/uniloud-click2call-public.template" \
  >/etc/logrotate.d/uniloud-click2call-public
chmod 0644 /etc/logrotate.d/uniloud-click2call-public

{
  printf 'version=%s\n' "$VERSION"
  printf 'installed_at_utc=%s\n' "$(date -u +%FT%TZ)"
  printf 'php_bin=%s\n' "$PHP_BIN"
  printf 'php_version=%s\n' "$PHP_VERSION"
  printf 'web_user=%s\n' "$WEB_USER"
  printf 'web_group=%s\n' "$WEB_GROUP"
  printf 'web_root=%s\n' "$WEB_ROOT"
  printf 'release_dir=%s\n' "$RELEASE_DIR"
} >"$STATE_DIR/install-manifest.txt"
chown "$WEB_USER:$WEB_GROUP" "$STATE_DIR/install-manifest.txt"
chmod 0640 "$STATE_DIR/install-manifest.txt"

printf '\nInstalled UniLoud Click-to-Call Public v%s.\n' "$VERSION"
printf 'PHP validation runtime: %s (%s)\n' "$PHP_BIN" "$PHP_VERSION"
printf 'Endpoints: %s\n' "$WEB_ROOT"
printf 'Configuration: %s/config.php\n' "$CONFIG_DIR"
printf '\nNo AMI account or Asterisk dialplan was changed automatically.\n'
