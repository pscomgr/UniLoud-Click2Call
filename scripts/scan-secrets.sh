#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
failures=0

check() {
  local description=$1 pattern=$2
  if rg -n -I \
    -g '!dist/**' \
    -g '!store-assets/**' \
    -g '!extension/icons/**' \
    -e "$pattern" "$ROOT"; then
    printf 'Potential secret detected: %s\n' "$description" >&2
    failures=$((failures + 1))
  fi
}

check 'private key' '-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'
check 'GitHub token' 'gh[pousr]_[A-Za-z0-9]{20,}'
check 'AWS access key' 'AKIA[0-9A-Z]{16}'
check 'Google API key' 'AIza[0-9A-Za-z_-]{30,}'
check 'Bearer token' 'Bearer[[:space:]]+[A-Za-z0-9._~+/-]{24,}'
if rg -n -I \
  -e '(KaseyaBmsClient|bms_employee_mapping|BMS_PASSWORD|serviceDeskApi|time_log_committed)' \
  "$ROOT/backend" "$ROOT/extension"; then
  printf 'Kaseya/BMS implementation detected in public runtime code.\n' >&2
  failures=$((failures + 1))
fi

if find "$ROOT" -type f \( -name '.env' -o -name '*.pem' -o -name '*.key' \) \
  -not -path "$ROOT/dist/*" -print -quit | grep -q .; then
  printf 'Potential credential file detected in source tree.\n' >&2
  failures=$((failures + 1))
fi

if ((failures > 0)); then
  exit 1
fi

printf 'PASS secret and public-boundary scan\n'
