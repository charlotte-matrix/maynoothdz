#!/usr/bin/env bash
set -euo pipefail

# Run from this theme directory (Plesk/Git hooks often start in the wrong cwd)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

# --- Node / npm for non-interactive deploys (no .bashrc / login shell) ---
# 1) Set NODENV_SHIMS in Plesk (Git → Repository → Environment) if needed, e.g.:
#    NODENV_SHIMS=/var/www/vhosts/YOURDOMAIN/.nodenv/shims
# 2) Or rely on subscription user's $HOME/.nodenv (typical Plesk layout)
# 3) Or use NVM_DIR + ~/.nvm/nvm.sh

if [ -n "${NODENV_SHIMS:-}" ] && [ -d "$NODENV_SHIMS" ]; then
  export PATH="$NODENV_SHIMS:$PATH"
elif [ -d "${HOME}/.nodenv/shims" ]; then
  export PATH="${HOME}/.nodenv/shims:$PATH"
elif [ -d "/var/www/vhosts/matrix-test.com/.nodenv/shims" ]; then
  # Legacy single-server path (remove when NODENV_SHIMS or HOME shims is set)
  export PATH="/var/www/vhosts/matrix-test.com/.nodenv/shims:$PATH"
fi

if ! command -v node >/dev/null 2>&1 && [ -s "${HOME}/.nvm/nvm.sh" ]; then
  # shellcheck source=/dev/null
  . "${HOME}/.nvm/nvm.sh"
  if command -v nvm >/dev/null 2>&1; then
    nvm use --silent default 2>/dev/null || nvm use --silent node 2>/dev/null || true
  fi
fi

hash -r 2>/dev/null || true

echo "==== Deploy build started: $(date) ===="
echo "cwd: $(pwd)"
echo "HOME: ${HOME:-}"
echo "Node: $(command -v node 2>/dev/null || echo 'not found') ($(node -v 2>/dev/null || echo '?'))"
echo "npm:  $(command -v npm 2>/dev/null || echo 'not found')"

if ! command -v npm >/dev/null 2>&1; then
  echo "ERROR: npm not in PATH. For Plesk, set NODENV_SHIMS to your site's .nodenv/shims, or install Node for the subscription user."
  exit 1
fi

# One-time dependency install (runs only if .deps_installed does NOT exist)
if [ ! -f .deps_installed ]; then
  echo "Deps marker not found, installing dependencies..."

  if command -v composer >/dev/null 2>&1; then
    echo "Running composer install..."
    composer install --no-dev --optimize-autoloader
  else
    echo "WARNING: composer not found, skipping PHP deps"
  fi

  echo "Running npm ci --ignore-scripts..."
  npm ci --ignore-scripts

  echo "Creating .deps_installed marker"
  touch .deps_installed
else
  echo ".deps_installed found, skipping dependency install"
fi

echo "Running npm run build..."
npm run build
echo "npm run build completed."

echo "==== Deploy build finished OK ===="
