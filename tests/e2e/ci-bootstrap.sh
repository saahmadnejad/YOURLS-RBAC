#!/usr/bin/env bash
# CI bootstrap: install YOURLS and activate the RBAC plugin.
# Usage: BASE_URL=http://127.0.0.1:8080 ./ci-bootstrap.sh
# Requires the Docker env up (docker compose up -d --build).
set -euo pipefail

BASE="${BASE_URL:-http://127.0.0.1:8080}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

# 1) Install YOURLS (idempotent: already-installed returns an error we ignore)
echo "Installing YOURLS…"
curl -sS -m 60 -X POST "$BASE/admin/install.php" -d 'install=Install+YOURLS' >/dev/null || true

# 2) Log in (scrape the login nonce from the form)
echo "Logging in…"
LOGIN_HTML="$(curl -sS -m 30 -c "$JAR" "$BASE/admin/")"
NONCE="$(printf '%s' "$LOGIN_HTML" | grep -oP 'name="nonce" value="\K[^"]+')"
if [ -z "$NONCE" ]; then
  echo "Could not scrape login nonce — is YOURLS reachable at $BASE?" >&2
  exit 1
fi
curl -sS -m 30 -b "$JAR" -c "$JAR" -o /dev/null \
  -d "username=admin&password=password123&action=login&nonce=$NONCE&submit=Login" \
  "$BASE/admin/index.php"

# 3) Activate rbac (scrape the manage_plugins nonce)
echo "Activating RBAC plugin…"
PLUGINS_HTML="$(curl -sS -m 30 -b "$JAR" "$BASE/admin/plugins.php")"
ACT_NONCE="$(printf '%s' "$PLUGINS_HTML" | grep -oP 'action=activate[^"]*nonce=\K[a-z0-9]+' | head -1)"
if [ -z "$ACT_NONCE" ]; then
  echo "Could not scrape activation nonce — login may have failed" >&2
  exit 1
fi
curl -sS -m 30 -b "$JAR" -o /dev/null \
  "$BASE/admin/plugins.php?action=activate&plugin=rbac&nonce=$ACT_NONCE"

# 4) Verify: the plugins page now offers "Deactivate" for rbac
sleep 2
echo "Verifying…"
PLUGINS_AFTER="$(curl -sS -m 30 -b "$JAR" "$BASE/admin/plugins.php")"
curl -sS -m 30 -b "$JAR" -o /dev/null -w 'plugin page: %{http_code}\n' \
  "$BASE/admin/plugins.php?page=rbac_users"
if printf '%s' "$PLUGINS_AFTER" | grep -q "action=deactivate&amp;plugin=rbac\|action=deactivate&plugin=rbac"; then
  echo "RBAC installed and activated."
else
  echo "RBAC does not appear active on the plugins page." >&2
  exit 1
fi
