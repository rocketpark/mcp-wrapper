#!/usr/bin/env bash
#
# check-faq-urls.sh — Health check every URL referenced by the JH FAQ Table.
#
# Why this exists:
#   The FAQ Table answer_template column embeds hardcoded jensenhughes.com
#   URLs. If JH marketing rearranges site URLs (Jan 26 LSFT slug change was
#   a real instance), the Table starts answering with 404 links and the bot
#   silently degrades. This script catches that.
#
# What it does:
#   1. Extracts every https://www.jensenhughes.com/... URL from
#      data/botpress-faq-table-seed.csv (the source of truth — sync from
#      live Botpress Table periodically if it diverges).
#   2. curl HEAD each URL. Treat 2xx + 3xx as healthy. 4xx / 5xx / timeouts
#      as failures. Reports specific 404s.
#   3. Optionally checks for silent redirects (final URL != requested URL)
#      via --strict.
#
# Usage:
#   ./scripts/check-faq-urls.sh                # basic
#   ./scripts/check-faq-urls.sh --strict       # also catch redirects
#   ./scripts/check-faq-urls.sh --verbose      # show every URL not just failures
#
# Exit codes:
#   0 = all URLs healthy
#   1 = one or more failures
#   2 = script error (CSV missing, curl unavailable, etc)
#
# Recommended cadence: nightly cron (M3 in JH-BOT-IMPROVEMENTS.md).

set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"
CSV="$REPO_ROOT/data/botpress-faq-table-seed.csv"

STRICT=0
VERBOSE=0
for arg in "$@"; do
  case "$arg" in
    --strict) STRICT=1 ;;
    --verbose|-v) VERBOSE=1 ;;
    -h|--help)
      sed -n '1,30p' "$0" | sed 's/^#//'
      exit 0
      ;;
  esac
done

if [ ! -f "$CSV" ]; then
  echo "ERROR: FAQ CSV not found at $CSV" >&2
  exit 2
fi
if ! command -v curl >/dev/null 2>&1; then
  echo "ERROR: curl required" >&2
  exit 2
fi

# Extract unique jensenhughes.com URLs from the CSV.
mapfile -t URLS < <(grep -oE 'https://www\.jensenhughes\.com[a-zA-Z0-9/_-]*' "$CSV" | sort -u)

if [ ${#URLS[@]} -eq 0 ]; then
  echo "WARN: no URLs found in $CSV (check the regex)"
  exit 2
fi

echo "Checking ${#URLS[@]} unique URL(s) from $(basename "$CSV")..."
echo ""

FAILED=()
REDIRECTED=()

for url in "${URLS[@]}"; do
  if [ $STRICT -eq 1 ]; then
    out=$(curl -o /dev/null -s -w "%{http_code} %{redirect_url}" -A "Mozilla/5.0 JH-FAQ-Health-Check" --max-time 10 "$url")
    code=$(echo "$out" | awk '{print $1}')
    redirect=$(echo "$out" | awk '{print $2}')
    if [ "$code" != "200" ]; then
      FAILED+=("$code $url")
      echo "✗ FAIL $code $url"
    elif [ -n "$redirect" ] && [ "$redirect" != "$url" ]; then
      REDIRECTED+=("$url → $redirect")
      echo "⚠ REDIRECT $url → $redirect"
    elif [ $VERBOSE -eq 1 ]; then
      echo "✓ $code $url"
    fi
  else
    code=$(curl -o /dev/null -s -w "%{http_code}" -L -A "Mozilla/5.0 JH-FAQ-Health-Check" --max-time 10 "$url")
    if [ "$code" != "200" ]; then
      FAILED+=("$code $url")
      echo "✗ FAIL $code $url"
    elif [ $VERBOSE -eq 1 ]; then
      echo "✓ $code $url"
    fi
  fi
done

echo ""
echo "─────────────────────────────────────────────"
echo "Total checked:  ${#URLS[@]}"
echo "Failed:         ${#FAILED[@]}"
if [ $STRICT -eq 1 ]; then
  echo "Redirected:     ${#REDIRECTED[@]}"
fi
echo "─────────────────────────────────────────────"

if [ ${#FAILED[@]} -gt 0 ]; then
  echo ""
  echo "Failures (fix these in $CSV + V6 prompt if applicable):"
  for f in "${FAILED[@]}"; do
    echo "  $f"
  done
  exit 1
fi

if [ $STRICT -eq 1 ] && [ ${#REDIRECTED[@]} -gt 0 ]; then
  echo ""
  echo "Redirects (consider updating to canonical):"
  for r in "${REDIRECTED[@]}"; do
    echo "  $r"
  done
  exit 1
fi

echo ""
echo "✓ All URLs healthy."
exit 0
