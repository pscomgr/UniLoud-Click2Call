#!/usr/bin/env bash
set -euo pipefail

: "${C2C_URL:?Set C2C_URL, e.g. https://pbx.example.com/uniloud-click2call}"
: "${C2C_API_ID:?Set C2C_API_ID}"
: "${C2C_API_SECRET:?Set C2C_API_SECRET}"
: "${C2C_EXTENSION:?Set C2C_EXTENSION}"
: "${C2C_DESTINATION:?Set C2C_DESTINATION}"
C2C_PROTOCOL=${C2C_PROTOCOL:-pjsip}

command -v curl >/dev/null 2>&1 || { echo "curl is required." >&2; exit 1; }
case "$C2C_URL" in
  https://*) ;;
  *) echo "C2C_URL must use HTTPS." >&2; exit 2 ;;
esac

body=$(
  C2C_API_ID="$C2C_API_ID" \
  C2C_API_SECRET="$C2C_API_SECRET" \
  C2C_EXTENSION="$C2C_EXTENSION" \
  C2C_DESTINATION="$C2C_DESTINATION" \
  C2C_PROTOCOL="$C2C_PROTOCOL" \
  python3 - <<'PY'
import json
import os
print(json.dumps({
    "apiId": os.environ["C2C_API_ID"],
    "apiSecret": os.environ["C2C_API_SECRET"],
    "extension": os.environ["C2C_EXTENSION"],
    "protocol": os.environ["C2C_PROTOCOL"],
    "destination": os.environ["C2C_DESTINATION"],
    "clientVersion": "1.4.0-cli-test",
}))
PY
)

curl --fail-with-body --silent --show-error \
  --connect-timeout 5 \
  --max-time 20 \
  --header 'Content-Type: application/json' \
  --data "$body" \
  "${C2C_URL%/}/originate_call.php"
printf '\n'
