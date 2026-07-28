#!/usr/bin/env bash
set -euo pipefail
umask 022

VERSION=1.4.0
ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
DIST=$ROOT/dist
BUILD=$(mktemp -d /tmp/uniloud-c2c-public-build.XXXXXX)
SOURCE_DATE_EPOCH=${SOURCE_DATE_EPOCH:-1785236400}
PHP_BIN=${C2C_PUBLIC_PHP_BIN:-}

cleanup() {
  rm -rf -- "$BUILD"
}
trap cleanup EXIT

for command in node rg zip sha256sum; do
  command -v "$command" >/dev/null 2>&1 || {
    echo "Missing build dependency: $command" >&2
    exit 1
  }
done

"$ROOT/scripts/scan-secrets.sh"
node "$ROOT/scripts/test-extension.mjs"

while IFS= read -r script; do
  bash -n "$script"
done < <(find "$ROOT" -type f -name '*.sh' -not -path "$DIST/*" | sort)

if [[ -z $PHP_BIN ]]; then
  PHP_BIN=$(command -v php 2>/dev/null || true)
fi
if [[ -n $PHP_BIN ]]; then
  version_id=$("$PHP_BIN" -r 'echo PHP_VERSION_ID;' 2>/dev/null || true)
  [[ $version_id =~ ^[0-9]+$ ]] \
    && ((version_id >= 80000 && version_id < 90000)) || {
      echo "Build validation requires PHP 8.x." >&2
      exit 1
    }
  while IFS= read -r file; do
    "$PHP_BIN" -l "$file" >/dev/null
  done < <(find "$ROOT/backend" -type f -name '*.php' | sort)
  "$PHP_BIN" "$ROOT/backend/tools/test_common.php"
elif [[ ${C2C_SKIP_PHP_TESTS:-0} == 1 ]]; then
  printf 'NOTICE native PHP tests skipped by explicit build override.\n' >&2
else
  echo "No PHP 8.x CLI found. Set C2C_PUBLIC_PHP_BIN or validate separately and use C2C_SKIP_PHP_TESTS=1." >&2
  exit 1
fi

node -e '
  const fs = require("fs");
  const manifest = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
  if (manifest.version !== process.argv[2]) {
    throw new Error(`manifest version ${manifest.version} != ${process.argv[2]}`);
  }
' "$ROOT/extension/manifest.json" "$VERSION"
rg -q "VERSION=$VERSION" "$ROOT/backend/install.sh"
rg -q "C2C_PUBLIC_BACKEND_VERSION = '$VERSION'" "$ROOT/backend/lib/common.php"

mkdir -p "$DIST" "$BUILD/package"
rm -f -- "$DIST"/*.zip "$DIST"/SHA256SUMS.txt

touch_tree() {
  find "$1" -exec touch -h -d "@$SOURCE_DATE_EPOCH" {} +
}

zip_tree() {
  local source=$1 output=$2
  (
    cd "$source"
    find . -type f -print | LC_ALL=C sort | zip -X -q "$output" -@
  )
}

BACKEND_DIR="UniLoud-Click-to-Call-Public-Backend-v$VERSION"
mkdir -p "$BUILD/package/$BACKEND_DIR"
cp -a "$ROOT/backend/." "$BUILD/package/$BACKEND_DIR/"
touch_tree "$BUILD/package/$BACKEND_DIR"
zip_tree "$BUILD/package" \
  "$DIST/UniLoud-Click-to-Call-Public-Backend-v$VERSION.zip"

mkdir -p "$BUILD/extension"
cp -a "$ROOT/extension/." "$BUILD/extension/"
rm -rf -- "$BUILD/extension/tests"
touch_tree "$BUILD/extension"
zip_tree "$BUILD/extension" \
  "$DIST/UniLoud-Click-to-Call-Public-Chrome-v$VERSION.zip"

mkdir -p "$BUILD/complete"
cp \
  "$DIST/UniLoud-Click-to-Call-Public-Backend-v$VERSION.zip" \
  "$DIST/UniLoud-Click-to-Call-Public-Chrome-v$VERSION.zip" \
  "$ROOT/README.md" \
  "$ROOT/README-GR.md" \
  "$ROOT/LICENSE" \
  "$ROOT/docs/INSTALLATION-GR.md" \
  "$ROOT/docs/INSTALLATION-EN.md" \
  "$BUILD/complete/"
touch_tree "$BUILD/complete"
zip_tree "$BUILD/complete" \
  "$DIST/UniLoud-Click-to-Call-Public-v$VERSION-Complete.zip"

mkdir -p "$BUILD/store/store-assets" "$BUILD/store/store"
cp "$DIST/UniLoud-Click-to-Call-Public-Chrome-v$VERSION.zip" "$BUILD/store/"
cp -a "$ROOT/store-assets/." "$BUILD/store/store-assets/"
rm -rf -- "$BUILD/store/store-assets/source"
cp -a "$ROOT/store/." "$BUILD/store/store/"
cp -a "$ROOT/docs/privacy" "$BUILD/store/"
touch_tree "$BUILD/store"
zip_tree "$BUILD/store" \
  "$DIST/UniLoud-Click-to-Call-Public-v$VERSION-Chrome-Store-Pack.zip"

(
  cd "$DIST"
  sha256sum ./*.zip | sed 's#  \./#  #' | LC_ALL=C sort >SHA256SUMS.txt
)

printf 'Built UniLoud Click-to-Call Public v%s\n' "$VERSION"
printf 'Artifacts: %s\n' "$DIST"
