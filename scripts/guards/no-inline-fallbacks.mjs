#!/usr/bin/env node
/* Guard: No inline modal fallback handlers in PHP templates
   - Scans sections/ (excludes sections/tools/) for inline <script> blocks that add delegated
     click handlers opening modals (fallbacks). All modal behavior must live in Vite modules.
   - Allows blocks neutralized with early-return comment containing "centralized via vite" or
     annotated with "guard: allow-inline-fallbacks". Exits non-zero on offenders. */
import { glob } from 'glob';
import fs from 'node:fs/promises';

const targets = await glob('sections/**/*.php', { ignore: ['sections/tools/**','**/node_modules/**','**/vendor/**','**/dist/**','**/.git/**'] });

function scriptsOf(html){ const out=[]; const re=/<script\b[^>]*>([\s\S]*?)<\/script>/gi; let m; while((m=re.exec(html))){ out.push(m[1]||''); } return out; }
function neutralized(js){ const t=(js||'').toLowerCase(); return t.includes('centralized via vite') || t.includes('guard: allow-inline-fallbacks'); }
function isFallback(js){
  if (neutralized(js)) return false;
  const s = js||'';
  const hasClick = /document\.addEventListener\(\s*['"]click['"]/i.test(s) || /addEventListener\(\s*['"]click['"]/i.test(s);
  const mentionsOpen = /(data-action\s*=\s*['"]open-[^'"]+['"])|closest\(\s*['"][^'"]*open-[^'"]*['"]/i.test(s);
  const modalHints = /(modal|overlay|ensure.*modal)/i.test(s);
  const toggles = /(classList\.(add|remove)\(\s*['"]show['"])|classList\.(add|remove)\(\s*['"]hidden['"]/i.test(s);
  return hasClick && mentionsOpen && (modalHints || toggles);
}

const offenders = [];
for (const file of targets){
  const html = await fs.readFile(file, 'utf8');
  const blocks = scriptsOf(html);
  for (const b of blocks){ if (isFallback(b)) { offenders.push(file); break; } }
}

if (offenders.length){
  console.error('[guard:no-inline-fallbacks] Inline modal fallback handlers found in:');
  offenders.forEach(f=>console.error(' -', f));
  console.error('\nMove modal behavior into Vite modules (src/js or src/modules). Use semantic classes for CSS.');
  process.exit(1);
}
console.log('[guard:no-inline-fallbacks] OK');
