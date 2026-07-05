#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
ROOT_DIR="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"
CONFIG_PATH="$ROOT_DIR/.gitleaks.toml"

MODE="${1:-repo}"
COMMIT_RANGE="${GITLEAKS_COMMIT_RANGE:-}"

has_modern_gitleaks() {
  gitleaks git --help >/dev/null 2>&1
}

run_native() {
  if [ "$MODE" = "staged" ]; then
    if has_modern_gitleaks; then
      exec gitleaks git --staged --redact --no-banner --config "$CONFIG_PATH"
    fi
    exec gitleaks protect --staged --redact --no-banner --config "$CONFIG_PATH"
  fi

  if [ "$MODE" = "history" ]; then
    if [ -z "$COMMIT_RANGE" ]; then
      echo "[gitleaks] ERROR: GITLEAKS_COMMIT_RANGE is required for history mode."
      exit 1
    fi
    if has_modern_gitleaks; then
      exec gitleaks git "$ROOT_DIR" --log-opts="$COMMIT_RANGE" --redact --no-banner --config "$CONFIG_PATH"
    fi
    exec gitleaks detect --source "$ROOT_DIR" --log-opts "$COMMIT_RANGE" --redact --no-banner --config "$CONFIG_PATH"
  fi

  if has_modern_gitleaks; then
    exec gitleaks dir "$ROOT_DIR" --redact --no-banner --config "$CONFIG_PATH"
  fi
  exec gitleaks detect --source "$ROOT_DIR" --redact --no-banner --config "$CONFIG_PATH"
}

run_docker() {
  if [ "$MODE" = "staged" ]; then
    exec docker run --rm -v "$ROOT_DIR:/repo" -w /repo zricethezav/gitleaks:latest \
      git --staged --redact --no-banner --config .gitleaks.toml
  fi

  if [ "$MODE" = "history" ]; then
    if [ -z "$COMMIT_RANGE" ]; then
      echo "[gitleaks] ERROR: GITLEAKS_COMMIT_RANGE is required for history mode."
      exit 1
    fi
    exec docker run --rm -v "$ROOT_DIR:/repo" -w /repo zricethezav/gitleaks:latest \
      git . --log-opts="$COMMIT_RANGE" --redact --no-banner --config .gitleaks.toml
  fi

  exec docker run --rm -v "$ROOT_DIR:/repo" -w /repo zricethezav/gitleaks:latest \
    dir . --redact --no-banner --config .gitleaks.toml
}

if command -v gitleaks >/dev/null 2>&1; then
  run_native
fi

if command -v docker >/dev/null 2>&1; then
  echo "[gitleaks] Native binary not found, running via Docker image."
  run_docker
fi

echo "[gitleaks] ERROR: gitleaks is not installed."
echo "[gitleaks] Install with Homebrew: brew install gitleaks"
echo "[gitleaks] Or install Docker and rerun commit."
exit 1
