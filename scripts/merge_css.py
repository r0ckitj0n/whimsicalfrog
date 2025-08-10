#!/usr/bin/env python3
"""
merge_css.py
-------------
Collect all *.css files from the supplied directories, deduplicate CSS rules and
output a master CSS file plus a provenance CSV mapping rule hashes to their
source files.

Usage:
  python scripts/merge_css.py output_css_path provenance_csv_path dir1 dir2 [...]

Example:
  python scripts/merge_css.py ../css/legacy_master.css merge_provenance.csv \
         ../css \
         ../backups ../backups/legacy_bigfiles/css /tmp/whimsicalfrog_repo

Notes:
* This script is intentionally dependency-free. It uses a very simple rule
  parser that splits on closing braces '}'. It is not a full CSS parser but is
  good enough for deduplicating typical rule blocks.
* Deduplication key = SHA256(selector + properties without whitespace).
* @media blocks are preserved verbatim and deduplicated on exact text match.
"""
import hashlib
import sys
from pathlib import Path
import csv


def hash_rule(rule: str) -> str:
    """Return a stable hash for a CSS rule string."""
    # Normalise whitespace for hashing
    condensed = "".join(rule.split())
    return hashlib.sha256(condensed.encode()).hexdigest()


def iter_css_files(dirs):
    """Yield Path objects for all .css files under given directories."""
    for d in dirs:
        p = Path(d).expanduser()
        if not p.exists():
            continue
        for css_file in p.rglob("*.css"):
            # Skip node_modules / vendor noise unless explicitly requested
            if any(part in {"node_modules", "vendor", ".git"} for part in css_file.parts):
                continue
            yield css_file


def extract_rules(css_text: str):
    """Very naive rule extractor: split on '}'. Returns list of rule strings."""
    rules = []
    buffer = []
    brace_depth = 0
    for char in css_text:
        buffer.append(char)
        if char == '{':
            brace_depth += 1
        elif char == '}':
            brace_depth -= 1
            if brace_depth == 0:
                # end of a top-level rule
                rules.append("".join(buffer).strip())
                buffer = []
    return rules


def main():
    if len(sys.argv) < 4:
        print("Usage: merge_css.py output_css provenance_csv dir1 [dir2 ...]")
        sys.exit(1)

    output_css = Path(sys.argv[1])
    provenance_csv = Path(sys.argv[2])
    dirs = sys.argv[3:]

    seen_hashes = set()
    merged_rules = []
    provenance_rows = []

    for css_path in iter_css_files(dirs):
        try:
            text = css_path.read_text(errors="ignore")
        except Exception as e:
            print(f"Skipping {css_path}: {e}")
            continue
        for rule in extract_rules(text):
            h = hash_rule(rule)
            if h in seen_hashes:
                continue
            seen_hashes.add(h)
            merged_rules.append(rule)
            provenance_rows.append({"hash": h, "file": str(css_path)})

    # Ensure output directory exists
    output_css.parent.mkdir(parents=True, exist_ok=True)
    output_css.write_text("\n\n".join(merged_rules))
    print(f"Wrote {len(merged_rules)} unique rules to {output_css}")

    with provenance_csv.open("w", newline="") as csvfile:
        writer = csv.DictWriter(csvfile, fieldnames=["hash", "file"])
        writer.writeheader()
        writer.writerows(provenance_rows)
    print(f"Wrote provenance CSV to {provenance_csv}")


if __name__ == "__main__":
    main()
