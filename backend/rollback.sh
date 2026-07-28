#!/usr/bin/env bash
set -euo pipefail

if [[ $EUID -ne 0 || $# -ne 1 ]]; then
  echo "Usage: sudo ./rollback.sh /var/backups/uniloud-click2call-public/upgrade-TIMESTAMP" >&2
  exit 2
fi
BACKUP=$(readlink -f -- "$1")
case "$BACKUP" in
  /var/backups/uniloud-click2call-public/upgrade-*) ;;
  *) echo "Unexpected rollback path." >&2; exit 1 ;;
esac
ROOTFS=$BACKUP/rootfs
[[ -d $ROOTFS && -f $BACKUP/files.list ]] || {
  echo "Incomplete rollback package." >&2
  exit 1
}
[[ ! -f $BACKUP/ROOTFS-SHA256SUMS ]] || \
  sha256sum -c "$BACKUP/ROOTFS-SHA256SUMS"

while IFS= read -r path; do
  [[ $path == /* && $path != / ]] || { echo "Unsafe rollback path." >&2; exit 1; }
  source=$ROOTFS$path
  if [[ -d $source && ! -L $source ]]; then
    install -d -m 0755 "$path"
    cp -a "$source/." "$path/"
  elif [[ -e $source || -L $source ]]; then
    install -d -m 0755 "$(dirname "$path")"
    cp -a "$source" "$path"
  fi
done <"$BACKUP/files.list"

if [[ -f $BACKUP/previous-active-link.txt ]]; then
  previous=$(<"$BACKUP/previous-active-link.txt")
  [[ $previous == /opt/uniloud-click2call-public/releases/* ]] || {
    echo "Unsafe previous release link." >&2
    exit 1
  }
  ln -sfn "$previous" /usr/local/lib/uniloud-click2call-public
fi
printf 'Rollback files restored. Run preflight before placing a call.\n'
