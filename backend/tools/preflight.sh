#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
CONFIG=${C2C_PUBLIC_CONFIG:-/etc/uniloud-click2call-public/config.php}
MANIFEST=/var/lib/uniloud-click2call-public/install-manifest.txt
PHP_BIN=${C2C_PUBLIC_PHP_BIN:-}
failures=0

pass() { printf 'PASS %s\n' "$*"; }
fail() { printf 'FAIL %s\n' "$*" >&2; failures=$((failures + 1)); }
info() { printf 'INFO %s\n' "$*"; }

if [[ -z $PHP_BIN && -r $MANIFEST ]]; then
  PHP_BIN=$(awk -F= '$1 == "php_bin" {print $2; exit}' "$MANIFEST")
fi
[[ -n $PHP_BIN ]] || PHP_BIN=$(command -v php 2>/dev/null || true)
if [[ $PHP_BIN != /* || ! -x $PHP_BIN ]]; then
  echo "No usable PHP CLI. Set C2C_PUBLIC_PHP_BIN." >&2
  exit 1
fi

version_id=$("$PHP_BIN" -r 'echo PHP_VERSION_ID;' 2>/dev/null || true)
version=$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;' 2>/dev/null || true)
if [[ $version_id =~ ^[0-9]+$ ]] && ((version_id >= 80000 && version_id < 90000)); then
  pass "PHP CLI $PHP_BIN ($version)"
  ((version_id >= 80200)) || info "PHP $version is compatible but no longer recommended; use supported PHP 8.2+"
else
  fail "PHP 8.x runtime required; found $version"
fi

while IFS= read -r file; do
  "$PHP_BIN" -l "$file" >/dev/null \
    && pass "PHP syntax ${file#$ROOT/}" \
    || fail "PHP syntax ${file#$ROOT/}"
done < <(find "$ROOT" -type f -name '*.php' | sort)

"$PHP_BIN" "$ROOT/tools/test_common.php" >/dev/null \
  && pass "backend unit tests" \
  || fail "backend unit tests"

if [[ -r $CONFIG ]]; then
  pass "configuration readable: $CONFIG"
  mode=$(stat -c '%a' "$CONFIG" 2>/dev/null || true)
  [[ $mode == 640 ]] && pass "configuration mode 0640" || fail "configuration mode is $mode, expected 640"
  C2C_PUBLIC_CONFIG=$CONFIG "$PHP_BIN" "$ROOT/tools/validate_config.php" >/dev/null \
    && pass "configuration validation" \
    || fail "configuration validation"
else
  fail "configuration missing or unreadable: $CONFIG"
fi

web_root=
if [[ -r $MANIFEST ]]; then
  web_root=$(awk -F= '$1 == "web_root" {print $2; exit}' "$MANIFEST")
fi
web_root=${web_root:-/var/www/html/uniloud-click2call}
for endpoint in originate_call.php list_extensions.php; do
  [[ -r $web_root/$endpoint ]] \
    && pass "endpoint installed: $web_root/$endpoint" \
    || fail "endpoint missing: $web_root/$endpoint"
done

if command -v asterisk >/dev/null 2>&1 && [[ -r $CONFIG ]]; then
  ring_all=$("$PHP_BIN" -r '
    $c = require $argv[1];
    echo !empty($c["telephony"]["ring_all_contacts"]) ? "yes" : "no";
  ' "$CONFIG" 2>/dev/null || true)
  if [[ $ring_all == yes ]]; then
    context=$("$PHP_BIN" -r '
      $c = require $argv[1];
      echo (string)($c["telephony"]["pjsip_ring_context"] ?? "");
    ' "$CONFIG")
    asterisk -rx 'core show function PJSIP_DIAL_CONTACTS' 2>/dev/null |
      grep -q 'PJSIP_DIAL_CONTACTS' \
      && pass "PJSIP_DIAL_CONTACTS available" \
      || fail "PJSIP_DIAL_CONTACTS unavailable"
    asterisk -rx "dialplan show $context" 2>/dev/null |
      grep -Eq "Context '$context'|\\[$context\\]" \
      && pass "dialplan context loaded: $context" \
      || fail "dialplan context missing: $context"
  fi
else
  info "Asterisk CLI unavailable; live dialplan checks skipped"
fi

if ((failures > 0)); then
  printf 'Preflight failed with %d finding(s).\n' "$failures" >&2
  exit 1
fi
printf 'Preflight passed. No call was placed.\n'
