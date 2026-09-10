#!/bin/bash
# Seed homepage with PACE hero + flexi blocks (Figma 3:2)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
THEME_DIR="$(dirname "$SCRIPT_DIR")"
WP_ROOT="$(realpath "$THEME_DIR/../../..")"

if [ -f "$THEME_DIR/.env" ]; then
  set -a
  # shellcheck disable=SC1091
  source "$THEME_DIR/.env"
  set +a
fi

if [ -n "${WP_PATH:-}" ]; then
  WP_ROOT="$(realpath "$WP_PATH")"
fi

if [ ! -f "$WP_ROOT/wp-config.php" ]; then
  echo "ERROR: WordPress not found at: $WP_ROOT"
  exit 1
fi

echo "PACE homepage setup"
echo "WordPress path: $WP_ROOT"
echo ""

FORCE_FLAG=""
if [[ "${1:-}" == "--force" ]]; then
  FORCE_FLAG="--force"
fi

run_wp() {
  wp --path="$WP_ROOT" "$@"
}

if run_wp help pace-home 2>/dev/null | grep -q 'setup'; then
  run_wp pace-home setup $FORCE_FLAG
else
  EVAL_FORCE='false'
  if [[ -n "$FORCE_FLAG" ]]; then
    EVAL_FORCE='true'
  fi
  run_wp eval "if (function_exists('matrix_pace_run_home_setup')) { \$r = matrix_pace_run_home_setup($EVAL_FORCE); echo json_encode(\$r) . PHP_EOL; } else { WP_CLI::error('matrix_pace_run_home_setup() not found.'); }"
fi

echo ""
echo "Done. Open your site front page (e.g. http://localhost:10014/)"
