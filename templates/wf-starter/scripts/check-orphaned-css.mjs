#!/usr/bin/env node
import { promises as fs } from 'node:fs';
import path from 'node:path';
import { globby } from 'globby';

/*
  Simple orphaned CSS detector for starter projects.
  - Lists CSS files under src/styles/
  - Marks ones imported by src/styles/main.css via @import
  - Reports remaining files as potential orphans
*/

const root = process.cwd();
const mainCssPath = path.join(root, 'src/styles/main.css');
let mainCss = '';
try { mainCss = await fs.readFile(mainCssPath, 'utf8'); } catch {}

const imports = new Set();
for (const m of mainCss.matchAll(/@import\s+url\(['\"]?(.+?)['\"]?\)/g)) {
  let imp = m[1];
  if (imp.startsWith('./')) imp = imp.slice(2);
  if (imp.startsWith('/')) imp = imp.slice(1);
  imports.add(imp);
}

const cssFiles = await globby(['src/styles/**/*.css']);
const orphans = [];
for (const file of cssFiles) {
  const rel = path.relative('src/styles', file).replace(/\\/g, '/');
  if (rel === 'main.css') continue;
  if (!imports.has(rel)) {
    orphans.push(file);
  }
}

if (orphans.length) {
  console.warn('Potential orphaned CSS files (not imported by src/styles/main.css):');
  for (const f of orphans) console.warn(' -', f);
  // Non-fatal in starter; just warn
  process.exit(0);
} else {
  console.log('No orphaned CSS detected.');
}
