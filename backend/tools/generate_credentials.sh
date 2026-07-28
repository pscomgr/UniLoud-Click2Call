#!/usr/bin/env bash
set -euo pipefail
umask 077

command -v openssl >/dev/null 2>&1 || {
  echo "openssl is required." >&2
  exit 1
}

API_ID=${1:-browser-$(openssl rand -hex 4)}
[[ $API_ID =~ ^[A-Za-z0-9_.-]{1,64}$ ]] || {
  echo "API ID may contain only letters, digits, dot, underscore and hyphen." >&2
  exit 2
}

API_SECRET=$(openssl rand -hex 32)
AMI_SECRET=$(openssl rand -hex 32)
API_HASH=$(printf '%s' "$API_SECRET" | openssl dgst -sha256 -r | awk '{print $1}')

cat <<EOF
API_ID=$API_ID
API_SECRET=$API_SECRET
API_SECRET_SHA256=$API_HASH
AMI_SECRET=$AMI_SECRET
EOF

printf '\nStore these values in a password manager now. The clear-text API secret\n'
printf 'goes only to the browser; config.php receives API_SECRET_SHA256.\n'
