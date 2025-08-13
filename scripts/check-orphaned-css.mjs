import { fileURLToPath } from 'url';
import path from 'path';
import fs from 'fs/promises';
import { glob } from 'glob';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const posix = (p) => p.split(path.sep).join('/');

async function readFileSafe(absPath) {
  try { return await fs.readFile(absPath, 'utf8'); } catch { return ''; }
}

function resolveImport(fromFileAbs, importSpec) {
  // Only resolve relative paths that end with .css
  if (!importSpec || !importSpec.endsWith('.css')) return null;
  const baseDir = path.dirname(fromFileAbs);
  const resolved = path.resolve(baseDir, importSpec);
  // Normalize to project-relative posix path
  const rel = posix(path.relative(projectRoot, resolved));
  return rel.startsWith('..') ? null : rel;
}

async function collectJsCssImports() {
  const files = await glob(['src/**/*.js', 'src/**/*.ts'], {
    cwd: projectRoot,
    nodir: true,
    ignore: ['**/node_modules/**', '**/dist/**', '**/backups/**']
  });
  const referenced = new Set();
  const importRe = /import\s+(?:[^'";]*from\s+)?["']([^"']+?\.css)["']/g;
  for (const rel of files) {
    const abs = path.join(projectRoot, rel);
    const src = await readFileSafe(abs);
    if (!src) continue;
    let m;
    while ((m = importRe.exec(src)) !== null) {
      const spec = m[1];
      const resolvedRel = resolveImport(abs, spec);
      if (resolvedRel) referenced.add(resolvedRel);
    }
  }
  return referenced;
}

async function collectCssImports(startRel) {
  const queue = [startRel];
  const seen = new Set();
  const referenced = new Set();
  // Regex variants to catch @import
  const reUrlWithQuotes = /@import\s+url\(\s*["']([^"']+?\.css)["']\s*\)\s*;?/g;
  const reUrlBare = /@import\s+url\(\s*([^\s)]+?\.css)\s*\)\s*;?/g;
  const rePlain = /@import\s+["']([^"']+?\.css)["']\s*;?/g;

  while (queue.length) {
    const rel = queue.shift();
    if (!rel || seen.has(rel)) continue;
    seen.add(rel);
    referenced.add(rel);

    const abs = path.join(projectRoot, rel);
    const css = await readFileSafe(abs);
    if (!css) continue;

    const addImport = (spec) => {
      const resolved = resolveImport(abs, spec);
      if (resolved && !seen.has(resolved)) queue.push(resolved);
    };

    let m;
    reUrlWithQuotes.lastIndex = 0;
    while ((m = reUrlWithQuotes.exec(css)) !== null) addImport(m[1]);
    reUrlBare.lastIndex = 0;
    while ((m = reUrlBare.exec(css)) !== null) addImport(m[1]);
    rePlain.lastIndex = 0;
    while ((m = rePlain.exec(css)) !== null) addImport(m[1]);
  }
  return referenced;
}

async function main() {
  const allCss = new Set(
    (await glob('src/styles/**/*.css', {
      cwd: projectRoot,
      nodir: true,
      ignore: ['**/node_modules/**', '**/dist/**', '**/backups/**']
    })).map((p) => posix(p))
  );

  // Seed from JS/TS imports
  const jsSeeds = await collectJsCssImports();

  // Expand through CSS @imports
  const referenced = new Set();
  for (const seed of jsSeeds) {
    if (!seed.endsWith('.css')) continue;
    const fromSeed = await collectCssImports(seed);
    for (const r of fromSeed) referenced.add(r);
  }

  // Optional: treat main.css as entry if present (belt-and-suspenders)
  if (allCss.has('src/styles/main.css')) {
    const viaMain = await collectCssImports('src/styles/main.css');
    for (const r of viaMain) referenced.add(r);
  }

  // Orphans = allCss - referenced
  const orphans = [...allCss].filter((p) => !referenced.has(p));

  if (orphans.length) {
    console.error('\nOrphaned CSS detected (not referenced by JS/TS or CSS @import):');
    for (const p of orphans) console.error('  -', p);
    console.error('\nSuggested actions:');
    console.error('  1) If needed, import the file from src/styles/main.css or a JS entry.');
    console.error('  2) If unused, move it to backups/unused_styles/.');
    process.exitCode = 1;
  } else {
    console.log('✅ No orphaned CSS detected.');
  }
}

await main();
