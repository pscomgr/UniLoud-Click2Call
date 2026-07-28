#!/usr/bin/env bash
set -euo pipefail
umask 077

[[ $EUID -eq 0 ]] || { echo "Run as root." >&2; exit 1; }
SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
stamp=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP=/var/backups/uniloud-click2call-public/upgrade-$stamp
ROOTFS=$BACKUP/rootfs
install -d -m 0700 "$ROOTFS"

WEB_ROOT=/var/www/html/uniloud-click2call
MANIFEST=/var/lib/uniloud-click2call-public/install-manifest.txt
if [[ -r $MANIFEST ]]; then
  installed_web_root=$(awk -F= '$1 == "web_root" {print substr($0, index($0, "=") + 1); exit}' "$MANIFEST")
  if [[ $installed_web_root == /* && $installed_web_root != / && $installed_web_root != /var/www/html ]]; then
    WEB_ROOT=$installed_web_root
  fi
fi
args=("$@")
for ((i = 0; i < ${#args[@]}; i++)); do
  if [[ ${args[$i]} == --web-root && $((i + 1)) -lt ${#args[@]} ]]; then
    WEB_ROOT=${args[$((i + 1))]}
  fi
done
[[ $WEB_ROOT == /* && $WEB_ROOT != / && $WEB_ROOT != /var/www/html ]] || {
  echo "Refusing unsafe web root during backup: $WEB_ROOT" >&2
  exit 1
}

paths=(
  /etc/uniloud-click2call-public
  /etc/asterisk/manager_custom.conf
  /etc/asterisk/extensions_custom.conf
  /etc/logrotate.d/uniloud-click2call-public
  "$WEB_ROOT/originate_call.php"
  "$WEB_ROOT/list_extensions.php"
)
: >"$BACKUP/files.list"
for path in "${paths[@]}"; do
  if [[ -e $path || -L $path ]]; then
    printf '%s\n' "$path" >>"$BACKUP/files.list"
    cp -a --parents -- "$path" "$ROOTFS"
  fi
done
if [[ -L /usr/local/lib/uniloud-click2call-public ]]; then
  readlink /usr/local/lib/uniloud-click2call-public >"$BACKUP/previous-active-link.txt"
fi
find "$ROOTFS" -type f -exec sha256sum {} + >"$BACKUP/ROOTFS-SHA256SUMS"

"$SCRIPT_DIR/install.sh" --replace-code "$@"

printf 'Upgrade staged. Backup: %s\n' "$BACKUP"
printf 'Run preflight and one controlled call before normal use.\n'
