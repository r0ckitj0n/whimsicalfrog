#!/usr/bin/env node

import fs from 'fs';
import path from 'path';

const ROOT = process.cwd();
const INCLUDE_DIRS = ['sections', 'src', 'components', 'partials'];
const EXCLUDES = ['backups', 'node_modules', '.git'];
const HAS_DATA_ICON_RE = /data-icon\s*=/i;
const HAS_ARIA_LABEL_RE = /aria-label\s*=/i;
const BUTTON_EMOJI_RE = /<button[^>]*>(?:\s*[\p{Emoji_Presentation}\p{Emoji}\u2190-\u21FF\u2300-\u23FF\u2600-\u27BF]+\s*)<\/button>/giu;

let errors = 0;

function walk(dir, out=[]) {
  const ents = fs.readdirSync(dir, { withFileTypes: true });
  for (const ent of ents) {
    if (EXCLUDES.some(x => dir.includes(x))) continue;
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) { walk(p, out); }
    else if (ent.isFile() && /\.(php|html)$/i.test(ent.name)) { out.push(p); }
  }
  return out;
}

function checkFile(file){
  const text = fs.readFileSync(file, 'utf8');
  // Rule 1: emoji-only buttons (likely icon buttons) are disallowed
  const emojiMatches = text.match(BUTTON_EMOJI_RE);
  if (emojiMatches && emojiMatches.length) {
    errors++;
    console.error(`[icons-guard] Emoji-only button detected in: ${file}`);
  }
  // Rule 2: if data-icon present, require aria-label
  if (HAS_DATA_ICON_RE.test(text) && !HAS_ARIA_LABEL_RE.test(text)) {
    errors++;
    console.error(`[icons-guard] data-icon used without aria-label in: ${file}`);
  }
}

for (const dir of INCLUDE_DIRS) {
  const abs = path.join(ROOT, dir);
  if (!fs.existsSync(abs)) continue;
  const files = walk(abs);
  files.forEach(checkFile);
}

if (errors) {
  const strict = process.env.WF_ICONS_STRICT === '1';
  const msg = `[icons-guard] Found ${errors} icon usage issue(s).${strict ? ' (strict mode)' : ' (non-strict, warning only)'}`;
  if (strict) {
    console.error(msg);
    process.exit(1);
  } else {
    console.warn(msg);
    process.exit(0);
  }
} else {
  console.log('[icons-guard] Icon usage looks good.');
}
