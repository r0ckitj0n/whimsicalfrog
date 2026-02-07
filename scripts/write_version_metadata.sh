#!/bin/bash
set -euo pipefail

# write_version_metadata.sh
# Writes lightweight version metadata consumed by /api/version_info.php.
# Output: dist/version-meta.json
#
# Usage:
#   ./scripts/write_version_metadata.sh
#   ./scripts/write_version_metadata.sh --deployed

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

MARK_DEPLOYED=0
if [ "${1:-}" = "--deployed" ]; then
  MARK_DEPLOYED=1
fi

now_utc="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
commit_hash="$(git rev-parse HEAD 2>/dev/null || true)"
commit_short_hash="$(git rev-parse --short HEAD 2>/dev/null || true)"
commit_subject="$(git log -1 --pretty=%s 2>/dev/null || true)"
commit_committed_at="$(git log -1 --pretty=%cI 2>/dev/null || true)"

export WF_VERSION_NOW_UTC="$now_utc"
export WF_VERSION_COMMIT_HASH="$commit_hash"
export WF_VERSION_COMMIT_SHORT_HASH="$commit_short_hash"
export WF_VERSION_COMMIT_SUBJECT="$commit_subject"
export WF_VERSION_COMMIT_COMMITTED_AT="$commit_committed_at"
export WF_VERSION_MARK_DEPLOYED="$MARK_DEPLOYED"

node <<'EOF'
const fs = require('fs');
const path = require('path');

const projectRoot = process.cwd();
const outputPath = path.join(projectRoot, 'dist', 'version-meta.json');
const nowUtc = process.env.WF_VERSION_NOW_UTC || null;
const markDeployed = process.env.WF_VERSION_MARK_DEPLOYED === '1';

let existing = {};
if (fs.existsSync(outputPath)) {
  try {
    existing = JSON.parse(fs.readFileSync(outputPath, 'utf8'));
  } catch {
    existing = {};
  }
}

const next = {
  schema_version: 1,
  updated_at: nowUtc,
  commit_hash: process.env.WF_VERSION_COMMIT_HASH || existing.commit_hash || null,
  commit_short_hash: process.env.WF_VERSION_COMMIT_SHORT_HASH || existing.commit_short_hash || null,
  commit_subject: process.env.WF_VERSION_COMMIT_SUBJECT || existing.commit_subject || null,
  commit_committed_at: process.env.WF_VERSION_COMMIT_COMMITTED_AT || existing.commit_committed_at || null,
  built_at: nowUtc,
  deployed_at: markDeployed ? nowUtc : (existing.deployed_at || null)
};

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
fs.writeFileSync(outputPath, `${JSON.stringify(next, null, 2)}\n`, 'utf8');
console.log(`[version-meta] wrote ${path.relative(projectRoot, outputPath)}`);
EOF
