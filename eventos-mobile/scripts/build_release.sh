#!/usr/bin/env bash
# Production-oriented release build with obfuscation + dart-defines.
# Fill in values from your CI secrets / secure env — never hardcode them here.
#
# Usage:
#   export API_BASE_URL=https://api.example.com/api/v1/
#   export EVENT_SUBDOMAIN=yourevent
#   export REVERB_KEY=...
#   export REVERB_HOST=reverb.example.com
#   ./scripts/build_release.sh apk   # or appbundle / ipa

set -euo pipefail

TARGET="${1:-apk}"
SYMBOLS_DIR="${SYMBOLS_DIR:-./build/symbols}"
mkdir -p "$SYMBOLS_DIR"

COMMON_DEFINES=(
  "--dart-define=API_BASE_URL=${API_BASE_URL:?API_BASE_URL required}"
  "--dart-define=EVENT_SUBDOMAIN=${EVENT_SUBDOMAIN:?EVENT_SUBDOMAIN required}"
  "--dart-define=REVERB_KEY=${REVERB_KEY:?REVERB_KEY required}"
  "--dart-define=REVERB_HOST=${REVERB_HOST:?REVERB_HOST required}"
  "--dart-define=REVERB_PORT=${REVERB_PORT:-443}"
  "--dart-define=REVERB_SCHEME=${REVERB_SCHEME:-https}"
)

# Optional: --dart-define=SSL_PINS=hex1;hex2
if [[ -n "${SSL_PINS:-}" ]]; then
  COMMON_DEFINES+=("--dart-define=SSL_PINS=$SSL_PINS")
fi

case "$TARGET" in
  apk)
    flutter build apk --release --obfuscate --split-debug-info="$SYMBOLS_DIR" \
      "${COMMON_DEFINES[@]}"
    ;;
  appbundle)
    flutter build appbundle --release --obfuscate --split-debug-info="$SYMBOLS_DIR" \
      "${COMMON_DEFINES[@]}"
    ;;
  ipa)
    flutter build ipa --release --obfuscate --split-debug-info="$SYMBOLS_DIR" \
      "${COMMON_DEFINES[@]}"
    ;;
  *)
    echo "Unknown target: $TARGET (use apk|appbundle|ipa)" >&2
    exit 1
    ;;
esac

echo "Store $SYMBOLS_DIR securely for crash symbolication. Do not ship it in the app."
