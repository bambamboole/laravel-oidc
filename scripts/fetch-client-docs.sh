#!/usr/bin/env sh
# Fetch the laravel-oidc-client docs pages into this site's content tree so they
# publish under /client/. Runs on docs deployment (and locally on demand); the
# sidebar group only appears when the directory exists.
set -eu

DEST="docs/content/docs/client"
REF="${CLIENT_DOCS_REF:-main}"
ARCHIVE="https://github.com/bambamboole/laravel-oidc-client/archive/refs/heads/${REF}.tar.gz"

rm -rf "$DEST"
mkdir -p "$DEST"

if curl -fsSL "$ARCHIVE" | tar -xz --strip-components=3 -C "$DEST" "laravel-oidc-client-${REF}/docs/content"; then
  echo "Fetched client docs (${REF}): $(ls "$DEST" | tr '\n' ' ')"
else
  echo "warning: could not fetch client docs from ${ARCHIVE}; building without the Client section" >&2
  rm -rf "$DEST"
fi
