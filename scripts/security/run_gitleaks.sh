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
  if has_modern_gitleaks; then
    if [ "$MODE" = "staged" ]; then
      exec gitleaks git --staged --redact --no-banner --config "$CONFIG_PATH"
    fi
    if [ "$MODE" = "history" ]; then
      if [ -n "$COMMIT_RANGE" ]; then
        exec gitleaks git --log-opts "$COMMIT_RANGE" --redact --no-banner --config "$CONFIG_PATH"
      fi
      exec gitleaks git --redact --no-banner --config "$CONFIG_PATH"
    fi
    exec gitleaks dir "$ROOT_DIR" --redact --no-banner --config "$CONFIG_PATH"
  fi

  if [ "$MODE" = "staged" ]; then
    exec gitleaks protect --staged --redact --no-banner --config "$CONFIG_PATH" --source "$ROOT_DIR"
  fi
  if [ "$MODE" = "history" ]; then
    if [ -n "$COMMIT_RANGE" ]; then
      exec gitleaks detect --log-opts "$COMMIT_RANGE" --redact --no-banner --config "$CONFIG_PATH" --source "$ROOT_DIR"
    fi
    exec gitleaks detect --redact --no-banner --config "$CONFIG_PATH" --source "$ROOT_DIR"
  fi
  exec gitleaks detect --no-git --redact --no-banner --config "$CONFIG_PATH" --source "$ROOT_DIR"
}

run_docker() {
  if [ "$MODE" = "staged" ]; then
    exec docker run --rm -v "$ROOT_DIR:/repo" -w /repo zricethezav/gitleaks:latest \
      git --staged --redact --no-banner --config .gitleaks.toml
  fi
  if [ "$MODE" = "history" ]; then
    if [ -n "$COMMIT_RANGE" ]; then
      exec docker run --rm -v "$ROOT_DIR:/repo" -w /repo zricethezav/gitleaks:latest \
        git --log-opts "$COMMIT_RANGE" --redact --no-banner --config .gitleaks.toml
    fi
    exec docker run --rm -v "$ROOT_DIR:/repo" -w /repo zricethezav/gitleaks:latest \
      git --redact --no-banner --config .gitleaks.toml
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
