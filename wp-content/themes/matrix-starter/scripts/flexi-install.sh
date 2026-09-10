#!/bin/bash
# =============================================================================
# flexi-install.sh — Matrix Starter project bootstrap
# =============================================================================
#
# WHAT THIS IS
#   One-shot installer for a new WordPress site using the matrix-starter theme.
#   Run from the theme root:  npm run flexi:install
#   Or from Local “Open Site Shell” (site must be running for activation).
#
# WHAT IT DOES
#   1. Detects WordPress root (WP_PATH in .env, or ../../../.. from scripts/)
#   2. Clones Matrix GitHub plugins into wp-content/plugins/ (if missing)
#   3. Installs common plugins from WordPress.org via WP-CLI
#   4. Activates theme + plugins when WP-CLI can reach the database
#   5. Configures Password Protected for staging (site lock, matrixYEAR password)
#
# CUSTOM PLUGINS (cloned)
#   • matrix-component-importer — flexi/component import UI
#   • matrix-sitemap-generator  — sitemap
#   • matrix-content-gathering  — client content form + CSV flexi import/export
#     https://github.com/bernardhanna/matrix-content-gathering
#
# DOES NOT
#   • Build theme CSS/JS (use npm run build)
#   • Install ACF Pro (required separately)
#   • Run theme pace:* setup seeders (see package.json)
#
# FLAGS
#   --force-activate  Attempt activation even if DB probe fails
#
# See scripts/README.md for full documentation.
# =============================================================================

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Matrix Starter — flexi-install (project bootstrap)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "  Reminder: this script clones Matrix plugins + activates the"
echo "  theme. It does NOT run npm build or install ACF Pro."
echo ""
echo "  Docs: scripts/README.md"
echo ""

set -euo pipefail

# ---------------- Helpers ----------------
run_wp_cmd() {
  local timeout="${1:-0}"
  shift
  local -a cmd=("$@")
  local wp_pid waiter_pid status

  if [ "$timeout" -le 0 ]; then
    "${cmd[@]}"
    return $?
  fi

  set +e
  "${cmd[@]}" &
  wp_pid=$!
  (
    sleep "$timeout"
    if kill -0 "$wp_pid" 2>/dev/null; then
      echo "⏳ WP-CLI timed out after ${timeout}s"
      kill -9 "$wp_pid" 2>/dev/null
    fi
  ) &
  waiter_pid=$!
  wait "$wp_pid"
  status=$?
  kill "$waiter_pid" 2>/dev/null || true
  wait "$waiter_pid" 2>/dev/null || true
  set -e
  return $status
}

run_wp() {
  run_wp_cmd "$WP_TIMEOUT" wp --path="$WP_ROOT" --skip-plugins --skip-themes "$@"
}

# No short timeout — plugin install/download needs more time.
run_wp_install() {
  run_wp_cmd "$WP_TIMEOUT_INSTALL" wp --path="$WP_ROOT" --skip-plugins --skip-themes "$@"
}

# WP-CLI with plugins loaded (needed for plugin option filters).
run_wp_plugins() {
  run_wp_cmd "$WP_TIMEOUT" wp --path="$WP_ROOT" "$@"
}

# Local WP sites often use DB_HOST=localhost, which hits the wrong MySQL when Homebrew
# mysql is installed. Resolve the Local socket from sites.json and patch wp-config.
local_detect_db_host() {
  local sites_json="${HOME}/Library/Application Support/Local/sites.json"

  if [ ! -f "$sites_json" ] || [ ! -d "$WP_ROOT" ]; then
    return 1
  fi

  python3 - "$WP_ROOT" "$sites_json" <<'PY' 2>/dev/null || return 1
import json, os, sys
wp_root = os.path.realpath(sys.argv[1])
sites_path = sys.argv[2]
with open(sites_path, encoding="utf-8") as fh:
    sites = json.load(fh)
home = os.path.expanduser("~")
for site_id, site in sites.items():
    raw = site.get("path", "")
    site_path = os.path.realpath(raw.replace("~/", home + "/").replace("~", home))
    if wp_root != site_path and not wp_root.startswith(site_path + os.sep):
        continue
    sock = os.path.join(home, "Library/Application Support/Local/run", site_id, "mysql/mysqld.sock")
    if os.path.exists(sock):
        print(f"localhost:{sock}")
        raise SystemExit(0)
raise SystemExit(1)
PY
}

ensure_wp_db_connection() {
  if run_wp option get siteurl >/dev/null 2>&1; then
    return 0
  fi

  local db_host
  db_host="$(local_detect_db_host)" || return 1

  echo "🔧 Local site detected — updating DB_HOST to use Local’s MySQL socket (for WP-CLI)."
  echo "   (Needed when running outside Local’s “Open Site Shell”.)"

  set +e
  run_wp config set DB_HOST "$db_host" --type=constant >/dev/null 2>&1
  set -e

  if run_wp option get siteurl >/dev/null 2>&1; then
    echo "✅ Database reachable."
    return 0
  fi

  return 1
}

configure_password_protected() {
  if ! command -v wp >/dev/null 2>&1; then
    return 0
  fi

  set +e
  run_wp_plugins plugin is-active password-protected >/dev/null 2>&1
  local active=$?
  set -e
  if [ $active -ne 0 ]; then
    echo "ℹ️ Password Protected not active — skipping site lock config."
    return 0
  fi

  local year plain
  year="$(date +%Y)"
  # Staging/crawler gate only — default matrix2026-style password is fine in-repo.
  plain="${MATRIX_SITE_PASSWORD:-matrix${year}}"

  echo ""
  echo "🔒 Password Protected (staging defaults)"
  echo "   • Whole-site protection: enabled"
  echo "   • Allow administrators: yes"
  echo "   • Remember me: yes (21 days)"
  echo "   • Password: ${plain}"

  set +e
  run_wp_plugins eval "
\$plain = '${plain}';
update_option( 'password_protected_status', 1 );
update_option( 'password_protected_administrators', 1 );
update_option( 'password_protected_remember_me', 1 );
update_option( 'password_protected_remember_me_lifetime', 21 );
update_option( 'password_protected_password', \$plain );
echo 'ok';
"
  local cfg=$?
  set -e

  if [ $cfg -eq 0 ]; then
    echo "✅ Password Protected saved."
  else
    echo "⚠️ Password Protected config failed — set manually under Settings → Password Protected."
  fi
}

configure_wp_mail_smtp() {
  if ! command -v wp >/dev/null 2>&1; then
    return 0
  fi

  set +e
  run_wp_plugins plugin is-active wp-mail-smtp >/dev/null 2>&1
  local active=$?
  set -e
  if [ $active -ne 0 ]; then
    echo "ℹ️ WP Mail SMTP not active — skipping mail config."
    return 0
  fi

  if [ -z "${MATRIX_SMTP_GOOGLE_CLIENT_SECRET:-}" ]; then
    echo ""
    echo "ℹ️ WP Mail SMTP: set MATRIX_SMTP_GOOGLE_CLIENT_SECRET in theme .env (see .env.example), then re-run."
    return 0
  fi

  export MATRIX_SMTP_FROM_EMAIL="${MATRIX_SMTP_FROM_EMAIL:-devs@matrixinternet.ie}"
  export MATRIX_SMTP_GOOGLE_CLIENT_ID="${MATRIX_SMTP_GOOGLE_CLIENT_ID:-759522357864-kbds2oh9meatcsjiqfd35obhpvrt08s9.apps.googleusercontent.com}"
  export MATRIX_SMTP_FROM_NAME="${MATRIX_SMTP_FROM_NAME:-}"
  export MATRIX_PROJECT_NAME="${MATRIX_PROJECT_NAME:-}"

  echo ""
  echo "📧 WP Mail SMTP (staging defaults)"
  echo "   • From email: ${MATRIX_SMTP_FROM_EMAIL} (force on)"
  echo "   • From name: site title or MATRIX_SMTP_FROM_NAME / MATRIX_PROJECT_NAME (force on)"
  echo "   • Mailer: Google / Gmail (manual app — not one-click)"
  echo "   • Google redirect URI (set in Cloud Console): https://connect.wpmailsmtp.com/google/"
  echo "   • OAuth: configure in WP admin → WP Mail SMTP → Authorize later"

  set +e
  run_wp_plugins eval-file "$SCRIPT_DIR/wp-mail-smtp-bootstrap.php"
  local smtp_cfg=$?
  set -e

  if [ $smtp_cfg -eq 0 ]; then
    echo "✅ WP Mail SMTP saved."
  else
    echo "⚠️ WP Mail SMTP config failed — set manually under Settings → WP Mail SMTP."
  fi
}

clone_plugin_repo() {
  local dir="$1"
  local repo="$2"
  local label="$3"

  if [ ! -d "$dir" ]; then
    echo "📦 Cloning ${label}..."
    git clone "$repo" "$dir"
  else
    echo "✅ ${label} already exists ($(basename "$dir"))."
  fi

  if [ -f "$dir/composer.json" ] && command -v composer >/dev/null 2>&1; then
    if [ ! -d "$dir/vendor" ]; then
      echo "📦 Running composer install in $(basename "$dir")..."
      (cd "$dir" && composer install --no-interaction --prefer-dist 2>/dev/null) || \
        echo "⚠️ composer install failed for $(basename "$dir") (optional; continue)."
    fi
  fi
}

# --------------- Flags -------------------
FORCE_ACTIVATE="no"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --force-activate) FORCE_ACTIVATE="yes"; shift ;;
    -h|--help)
      echo "Usage: flexi-install.sh [--force-activate]"
      echo ""
      echo "  Bootstrap WordPress with matrix-starter theme + Matrix plugins."
      echo "  See scripts/README.md for details."
      exit 0
      ;;
    *) echo "Unknown option: $1 (try --help)"; exit 1 ;;
  esac
done

# --------------- Env ---------------------
if [ -f .env ]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
else
  echo "ℹ️ .env not found — proceeding with default path detection."
fi

WP_TIMEOUT="${WP_TIMEOUT:-15}"       # quick probes (option get, is-installed)
WP_TIMEOUT_INSTALL="${WP_TIMEOUT_INSTALL:-180}"  # plugin downloads can be slow

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -n "${WP_PATH:-}" ]; then
  WP_ROOT="$(realpath "$WP_PATH")"
else
  WP_ROOT="$(realpath "$SCRIPT_DIR/../../../..")"
fi
echo "📁 WordPress path: $WP_ROOT"

THEME_DIR="$(realpath "$SCRIPT_DIR/..")"
THEME_SLUG="$(basename "$THEME_DIR")"

PLUGINS_DIR="$WP_ROOT/wp-content/plugins"
mkdir -p "$PLUGINS_DIR"

# Custom plugins: "directory_name|git_url|human_label"
CUSTOM_PLUGINS=(
  "matrix-component-importer|https://github.com/bernardhanna/matrix-component-importer.git|Matrix Component Importer"
  "matrix-sitemap-generator|https://github.com/bernardhanna/matrix-sitemap-generator.git|Matrix Sitemap Generator"
  "matrix-content-gathering|https://github.com/bernardhanna/matrix-content-gathering.git|Matrix Content Gathering"
)

# WordPress.org slugs — installed via: wp plugin install <slug>
# (Packagist/wpackagist is possible with Composer, but WP-CLI is simpler here.)
PLUGINS_WP_ORG=(
  "classic-editor"              # WordPress Contributors
  "duplicate-page"              # mndpsingh287
  "password-protected"          # Password Protected
  "prevent-browser-caching"     # Kostya Tereshchuk
  "seo-by-rank-math"            # Rank Math SEO
  "wp-mail-smtp"                # WP Mail SMTP
)

# Plugin slugs for WP-CLI activate (folder/main-file.php)
CUSTOM_PLUGIN_ACTIVATE=(
  "matrix-component-importer"
  "matrix-sitemap-generator"
  "matrix-content-gathering/matrix-content-export.php"
)

# --------------- Clone custom repos ---------------
echo ""
echo "📦 Custom Matrix plugins"
echo "------------------------"
for entry in "${CUSTOM_PLUGINS[@]}"; do
  IFS='|' read -r slug repo label <<< "$entry"
  clone_plugin_repo "$PLUGINS_DIR/$slug" "$repo" "$label"
done

# --------------- WP-CLI detection -----------------
CAN_ACTIVATE="no"
DB_OK="no"

if command -v wp >/dev/null 2>&1; then
  if [ -f "$WP_ROOT/wp-config.php" ] || [ -f "$WP_ROOT/wp-load.php" ]; then
    echo ""
    echo "🧪 Probing WordPress via WP-CLI (timeout: ${WP_TIMEOUT}s)…"
    if ensure_wp_db_connection; then
      DB_OK="yes"
      CAN_ACTIVATE="yes"
    else
      echo "⚠️ Could not reach the WordPress database."
      echo "   • Start the site in Local (green “Running”), then re-run:"
      echo "     npm run flexi:install"
      echo "   • Or use Local → Site → Open Site Shell, then run the same command."
      echo "   • Plugins are already cloned; only activation/config was skipped."
      if [ "$FORCE_ACTIVATE" = "yes" ]; then
        CAN_ACTIVATE="yes"
        echo "👉 --force-activate: will still try WP-CLI (likely to fail until DB is up)."
      fi
    fi
  else
    echo "ℹ️ No wp-config.php at: $WP_ROOT (plugins cloned; activation skipped)."
  fi
else
  echo "ℹ️ WP-CLI not found (plugins cloned; activation skipped)."
fi

# --------------- Theme + plugins via WP-CLI -------
if [ "$CAN_ACTIVATE" != "no" ]; then
  THEME_ACT=0
  ACT_CUSTOM=0
  ACT_WP_ORG=0

  echo ""
  echo "🎨 Theme: $THEME_SLUG"
  if [ ! -d "$WP_ROOT/wp-content/themes/$THEME_SLUG" ]; then
    echo "ℹ️ Theme not in this install: wp-content/themes/$THEME_SLUG (skipping activation)."
  else
    set +e
    run_wp theme activate "$THEME_SLUG"
    THEME_ACT=$?
    set -e
    if [ ${THEME_ACT:-1} -ne 0 ]; then
      echo "⚠️ Theme activation reported an error (continuing)."
    else
      echo "✅ Theme activated."
    fi
  fi

  echo ""
  echo "🔌 WordPress.org plugins (install + activate)"
  MISSING_ORG=()
  for PLUGIN_SLUG in "${PLUGINS_WP_ORG[@]}"; do
    if ! run_wp plugin is-installed "$PLUGIN_SLUG" >/dev/null 2>&1; then
      MISSING_ORG+=("$PLUGIN_SLUG")
    fi
  done

  if [ ${#MISSING_ORG[@]} -eq 0 ]; then
    echo "✅ All WordPress.org plugins already installed."
    set +e
    run_wp_install plugin activate "${PLUGINS_WP_ORG[@]}"
    ACT_WP_ORG=$?
    set -e
  else
    echo "📦 Installing: ${MISSING_ORG[*]}"
    set +e
    run_wp_install plugin install "${MISSING_ORG[@]}" --activate
    ACT_WP_ORG=$?
    set -e
    if [ ${ACT_WP_ORG:-1} -ne 0 ]; then
      echo "⚠️ Batch install failed — trying one plugin at a time…"
      ACT_WP_ORG=0
      for PLUGIN_SLUG in "${MISSING_ORG[@]}"; do
        set +e
        run_wp_install plugin install "$PLUGIN_SLUG" --activate
        ONE=$?
        set -e
        if [ $ONE -ne 0 ]; then
          echo "   ✗ Failed: $PLUGIN_SLUG"
          ACT_WP_ORG=1
        else
          echo "   ✓ $PLUGIN_SLUG"
        fi
      done
    else
      echo "✅ WordPress.org plugins installed and activated."
    fi
  fi

  echo ""
  echo "🔌 Matrix plugins (activate)"
  set +e
  run_wp_install plugin activate "${CUSTOM_PLUGIN_ACTIVATE[@]}"
  ACT_CUSTOM=$?
  set -e

  echo ""
  echo "🔎 Status"
  set +e
  run_wp theme status "$THEME_SLUG" 2>/dev/null | sed -n '1,8p' || true
  for plugin_slug in "${CUSTOM_PLUGIN_ACTIVATE[@]}" "${PLUGINS_WP_ORG[@]}"; do
    run_wp plugin status "$plugin_slug" 2>/dev/null | sed -n '1,6p' || true
  done
  set -e

  if [ ${THEME_ACT:-0} -ne 0 ] || [ ${ACT_CUSTOM:-0} -ne 0 ] || [ ${ACT_WP_ORG:-0} -ne 0 ]; then
    echo ""
    echo "⚠️ One or more activations reported errors."
    if [ "$DB_OK" != "yes" ]; then
      echo "   Likely DB connectivity — start the site in Local and retry from Site Shell."
    fi
    echo "   Manual retry:"
    echo "   wp --path=\"$WP_ROOT\" theme activate \"$THEME_SLUG\" --skip-plugins --skip-themes"
    echo "   wp --path=\"$WP_ROOT\" plugin activate ${CUSTOM_PLUGIN_ACTIVATE[*]} --skip-plugins --skip-themes"
    echo "   wp --path=\"$WP_ROOT\" plugin activate ${PLUGINS_WP_ORG[*]} --skip-plugins --skip-themes"
  else
    echo "✅ Theme and plugins activated."
  fi

  if [ "$DB_OK" = "yes" ]; then
    configure_password_protected
    configure_wp_mail_smtp
  fi

  echo ""
  echo "📋 Plugin checklist"
  for PLUGIN_SLUG in "${PLUGINS_WP_ORG[@]}"; do
    if run_wp plugin is-installed "$PLUGIN_SLUG" >/dev/null 2>&1; then
      echo "   ✓ $PLUGIN_SLUG"
    else
      echo "   ✗ $PLUGIN_SLUG (missing — re-run install or: wp plugin install $PLUGIN_SLUG --activate)"
    fi
  done
  for plugin_slug in "${CUSTOM_PLUGIN_ACTIVATE[@]}"; do
    if run_wp plugin is-installed "$plugin_slug" >/dev/null 2>&1; then
      echo "   ✓ $plugin_slug"
    else
      echo "   ✗ $plugin_slug (missing)"
    fi
  done

else
  echo ""
  echo "ℹ️ Activation skipped (no WP-CLI or WordPress not detected)."
  echo "   Plugins are cloned under wp-content/plugins/. When ready:"
  echo "   wp --path=\"$WP_ROOT\" theme activate \"$THEME_SLUG\" --skip-plugins --skip-themes"
  echo "   wp --path=\"$WP_ROOT\" plugin activate ${CUSTOM_PLUGIN_ACTIVATE[*]} --skip-plugins --skip-themes"
  echo "   wp --path=\"$WP_ROOT\" plugin install ${PLUGINS_WP_ORG[*]}"
  echo "   wp --path=\"$WP_ROOT\" plugin activate ${PLUGINS_WP_ORG[*]} --skip-plugins --skip-themes"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Setup complete"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "  Theme:    $THEME_DIR"
echo "  Plugins:  $PLUGINS_DIR"
for entry in "${CUSTOM_PLUGINS[@]}"; do
  IFS='|' read -r slug _ _ <<< "$entry"
  echo "            • $slug"
done
echo ""
echo "  Next steps:"
echo "    • Install + activate ACF Pro (required)"
echo "    • npm run build  (theme assets)"
echo "    • Tools → Content Gathering  (matrix-content-gathering)"
echo "    • matrix-ci-admin-page  (component importer)"
if command -v date >/dev/null 2>&1; then
  echo ""
  echo "  Staging password (Password Protected): matrix$(date +%Y)"
fi
echo ""
