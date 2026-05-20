#!/usr/bin/env bash
#
# bp-deploy-safe.sh — guarded wrapper around `bp deploy`
#
# Why this exists:
#   Botpress historically does not 100% reliably preserve per-bot integration
#   config across schema updates. When you bump the craftcms-mcp integration
#   version and the bot installs the new version, the bot's mcpServerUrl
#   has been observed reset to the integration's default
#   (https://servicecurator.com) — silently breaking the bot until
#   manually re-entered. This script snapshots the bot's full Botpress
#   record before deploy + after, diffs the two, and yells if config
#   appears to have changed unintentionally.
#
# What it does:
#   1. Pre-deploy: GET /v1/admin/bots/<botId> → /tmp/bp-config-snapshot-before.json
#   2. Run: npx bp deploy "$@"        (passes through all CLI args)
#   3. Post-deploy: GET again → /tmp/bp-config-snapshot-after.json
#   4. diff -u — if any change, prints diff + restore guidance and exits 2.
#
# Usage:
#   ./scripts/bp-deploy-safe.sh         # safe equivalent of `bp deploy`
#   ./scripts/bp-deploy-safe.sh -v      # bp deploy --verbose, etc
#
# Configuration:
#   BOTPRESS_PAT     defaults to ~/.botpress/profiles.json::default.token
#   BOTPRESS_BOT_ID  defaults to the JH bot id (208ffbe5-...). Override per env.
#
# Exit codes:
#   0  = deploy ok, no config drift
#   1  = pre-flight check failed (missing PAT, bot id, jq, etc)
#   2  = deploy ok BUT config drift detected — RESTORE NEEDED
#   3  = bp deploy itself failed
#
# Dependencies: jq, curl, node (for reading profiles.json), npx bp.

set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
INTEGRATION_DIR="$( cd "$SCRIPT_DIR/../botpress-integration" && pwd )"

PROFILE_PATH="${BOTPRESS_PROFILE_PATH:-$HOME/.botpress/profiles.json}"
BOT_ID="${BOTPRESS_BOT_ID:-208ffbe5-a209-4a10-a52c-d79de4577f45}"
API_BASE="${BOTPRESS_API_URL:-https://api.botpress.cloud}"

SNAP_BEFORE="${BPDEPLOY_SNAP_BEFORE:-/tmp/bp-config-snapshot-before.json}"
SNAP_AFTER="${BPDEPLOY_SNAP_AFTER:-/tmp/bp-config-snapshot-after.json}"
DIFF_FILE="${BPDEPLOY_DIFF:-/tmp/bp-config-diff.txt}"

err() { echo "ERROR: $*" >&2; }
log() { echo "→ $*"; }

# --- pre-flight checks ---

if ! command -v jq >/dev/null 2>&1; then
  err "jq required (brew install jq)"
  exit 1
fi
if ! command -v curl >/dev/null 2>&1; then
  err "curl required"
  exit 1
fi

PAT="${BOTPRESS_PAT:-}"
if [ -z "$PAT" ] && [ -f "$PROFILE_PATH" ]; then
  PAT=$(node -e "
    try {
      const p = JSON.parse(require('fs').readFileSync('$PROFILE_PATH','utf8'));
      const k = Object.keys(p)[0];
      process.stdout.write(p[k].token || '');
    } catch (e) { process.exit(1); }
  " 2>/dev/null || true)
fi

if [ -z "$PAT" ]; then
  err "No Botpress PAT. Set BOTPRESS_PAT env or ensure $PROFILE_PATH has a token."
  exit 1
fi

# --- snapshot helper ---

snapshot() {
  local out="$1"
  curl -fsS \
    -H "Authorization: Bearer $PAT" \
    -H "Accept: application/json" \
    "$API_BASE/v1/admin/bots/$BOT_ID" > "$out.tmp"
  jq '.' "$out.tmp" > "$out"
  rm -f "$out.tmp"
  echo "$out ($(wc -c < "$out" | tr -d ' ') bytes)"
}

# --- step 1: snapshot before ---

log "Fetching bot record BEFORE deploy (bot=$BOT_ID)..."
if ! snapshot "$SNAP_BEFORE"; then
  err "Failed to fetch bot record. PAT scope insufficient or bot id wrong."
  err "Proceeding with deploy anyway, but post-deploy verification will be skipped."
  SKIP_POST=1
fi

# --- step 2: bp deploy (passthrough args) ---

log "Running: npx bp deploy $*"
cd "$INTEGRATION_DIR"
if ! npx bp deploy "$@"; then
  err "bp deploy failed."
  exit 3
fi

# --- step 3: snapshot after + diff ---

if [ "${SKIP_POST:-0}" = "1" ]; then
  log "Skipping post-deploy verification (no PAT for fetch)."
  exit 0
fi

log "Waiting 5s for Botpress to settle..."
sleep 5

log "Fetching bot record AFTER deploy..."
snapshot "$SNAP_AFTER"

log "Diffing snapshots..."
if diff -u "$SNAP_BEFORE" "$SNAP_AFTER" > "$DIFF_FILE"; then
  echo ""
  echo "✓ DEPLOY SAFE: no bot config drift detected."
  exit 0
fi

echo ""
echo "⚠ CONFIG DRIFT DETECTED after deploy. Diff at $DIFF_FILE:"
echo "─────────────────────────────────────────────────────────"
cat "$DIFF_FILE"
echo "─────────────────────────────────────────────────────────"
echo ""
echo "RESTORE STEPS:"
echo "  1. Open Studio: https://studio.botpress.cloud/$BOT_ID"
echo "  2. Integrations → craftcms-mcp → Configuration"
echo "  3. Compare on-screen values vs $SNAP_BEFORE"
echo "  4. Re-enter any reset fields, Save, Publish"
echo ""
exit 2
