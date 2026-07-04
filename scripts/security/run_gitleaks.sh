#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
ROOT_DIR="$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)"
CONFIG_PATH="$ROOT_DIR/.gitleaks.toml"

MODE="${1:-repo}"

history_log_opts() {
  if [ -n "${GITLEAKS_COMMIT_RANGE:-}" ]; then
    printf '%s\n' "$GITLEAKS_COMMIT_RANGE"
    return
  fi

  if git -C "$ROOT_DIR" rev-parse --verify HEAD^ >/dev/null 2>&1; then
    printf '%s\n' 'HEAD^..HEAD'
    return
  fi

  printf '%s\n' 'HEAD'
}

has_modern_native_git_command() {
  gitleaks git --help >/dev/null 2>&1
}

has_modern_native_dir_command() {
  gitleaks dir --help >/dev/null 2>&1
}

run_native() {
  case "$MODE" in
    staged)
      if has_modern_native_git_command; then
        exec gitleaks git --staged --redact --no-banner --config "$CONFIG_PATH"
      fi
      exec gitleaks protect --staged --redact --no-banner --config "$CONFIG_PATH"
      ;;
    history)
      if has_modern_native_git_command; then
        exec gitleaks git --log-opts="$(history_log_opts)" --redact --no-banner --config "$CONFIG_PATH"
      fi
      exec gitleaks detect --source "$ROOT_DIR" --log-opts="$(history_log_opts)" --redact --no-banner --config "$CONFIG_PATH"
      ;;
    repo)
      if has_modern_native_dir_command; then
        exec gitleaks dir "$ROOT_DIR" --redact --no-banner --config "$CONFIG_PATH"
      fi
      exec gitleaks detect --no-git --source "$ROOT_DIR" --redact --no-banner --config "$CONFIG_PATH"
      ;;
    *)
      echo "[gitleaks] ERROR: unknown mode '$MODE'. Use repo, staged, or history."
      exit 2
      ;;
  esac
}

run_docker() {
  case "$MODE" in
    staged)
      exec docker run --rm -v "$ROOT_DIR:/repo" -w /repo zricethezav/gitleaks:latest \
        git --staged --redact --no-banner --config .gitleaks.toml
      ;;
    history)
      exec docker run --rm -v "$ROOT_DIR:/repo" -w /repo zricethezav/gitleaks:latest \
        git --log-opts="$(history_log_opts)" --redact --no-banner --config .gitleaks.toml
      ;;
    repo)
      exec docker run --rm -v "$ROOT_DIR:/repo" -w /repo zricethezav/gitleaks:latest \
        dir . --redact --no-banner --config .gitleaks.toml
      ;;
    *)
      echo "[gitleaks] ERROR: unknown mode '$MODE'. Use repo, staged, or history."
      exit 2
      ;;
  esac
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
