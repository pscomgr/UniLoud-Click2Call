#!/usr/bin/env bash
set -euo pipefail

PURGE_CONFIG=0
PURGE_DATA=0
PURGE_LOGS=0
WEB_ROOT=${C2C_PUBLIC_WEB_ROOT:-/var/www/html/uniloud-click2call}
MANIFEST=/var/lib/uniloud-click2call-public/install-manifest.txt
if [[ -z ${C2C_PUBLIC_WEB_ROOT:-} && -r $MANIFEST ]]; then
  installed_web_root=$(awk -F= '$1 == "web_root" {print substr($0, index($0, "=") + 1); exit}' "$MANIFEST")
  if [[ $installed_web_root == /* ]]; then
    WEB_ROOT=$installed_web_root
  fi
fi

while (($#)); do
  case "$1" in
    --purge-config) PURGE_CONFIG=1 ;;
    --purge-data) PURGE_DATA=1 ;;
    --purge-logs) PURGE_LOGS=1 ;;
    --web-root) WEB_ROOT=${2:-}; shift ;;
    --help)
      echo "Usage: sudo ./uninstall.sh [--web-root PATH] [--purge-config] [--purge-data] [--purge-logs]"
      exit 0
      ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
  esac
  shift
done

[[ $EUID -eq 0 ]] || { echo "Run as root." >&2; exit 1; }
[[ $WEB_ROOT == /* && $WEB_ROOT != / && $WEB_ROOT != /var/www/html ]] || {
  echo "Unsafe web root." >&2
  exit 1
}

rm -f -- "$WEB_ROOT/originate_call.php" "$WEB_ROOT/list_extensions.php"
rmdir "$WEB_ROOT" 2>/dev/null || true
rm -f -- /etc/logrotate.d/uniloud-click2call-public
if [[ -L /usr/local/lib/uniloud-click2call-public ]]; then
  target=$(readlink /usr/local/lib/uniloud-click2call-public)
  if [[ $target == /opt/uniloud-click2call-public/releases/1.4.0 ]]; then
    rm -f -- /usr/local/lib/uniloud-click2call-public
  fi
fi
if [[ -d /opt/uniloud-click2call-public/releases/1.4.0 ]]; then
  rm -rf -- /opt/uniloud-click2call-public/releases/1.4.0
fi

((PURGE_CONFIG == 0)) || rm -rf -- /etc/uniloud-click2call-public
((PURGE_DATA == 0)) || rm -rf -- /var/lib/uniloud-click2call-public
((PURGE_LOGS == 0)) || rm -rf -- /var/log/uniloud-click2call-public

printf 'Removed public v1.4.0 code. Configuration, data and logs were retained unless purged.\n'
