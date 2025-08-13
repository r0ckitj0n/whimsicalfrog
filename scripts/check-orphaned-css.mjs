import { fileURLToPath } from 'url';
import path from 'path';
import fs from 'fs/promises';
import { glob } from 'glob';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const posix = (p) => p.split(path.sep).join('/');
const hasFlag = (flag) => process.argv.includes(flag);
const WRITE = hasFlag('--write');

async function ensureDir(dir) {
  await fs.mkdir(dir, { recursive: true });
}

function ts() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return (
    d.getFullYear().toString() +
    pad(d.getMonth() + 1) +
    pad(d.getDate()) + '-' +
    pad(d.getHours()) +
    pad(d.getMinutes()) +
    pad(d.getSeconds())
  );
}

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
    if (!WRITE) {
      console.error('\nOrphaned CSS detected (not referenced by JS/TS or CSS @import):');
      for (const p of orphans) console.error('  -', p);
      console.error('\nSuggested actions:');
      console.error('  1) If needed, import the file from src/styles/main.css or a JS entry.');
      console.error('  2) If unused, move it to backups/unused_styles/.');
      console.error('     (Tip: run this script with --write to auto-archive)');
      process.exitCode = 1;
      return;
    }
    // Write mode: move to backups/unused_styles/ preserving relative path under src/styles/
    const backupRoot = path.join(projectRoot, 'backups', 'unused_styles');
    await ensureDir(backupRoot);
    console.log(`\n--write enabled. Archiving ${orphans.length} orphan(s) to ${posix(path.relative(projectRoot, backupRoot))}`);
    for (const rel of orphans) {
      // Expect rel like 'src/styles/...' — compute path under backups/unused_styles/
      const relFromStyles = rel.startsWith('src/styles/') ? rel.slice('src/styles/'.length) : path.basename(rel);
      const srcAbs = path.join(projectRoot, rel);
      const destAbsBase = path.join(backupRoot, relFromStyles);
      const destDir = path.dirname(destAbsBase);
      await ensureDir(destDir);
      let destAbs = destAbsBase;
      // If destination exists, add timestamp suffix before extension
      try {
        await fs.access(destAbs);
        const ext = path.extname(destAbsBase);
        const baseNoExt = destAbsBase.slice(0, -ext.length);
        destAbs = `${baseNoExt}.backup-${ts()}${ext}`;
      } catch {
        // does not exist; ok
      }
      await fs.rename(srcAbs, destAbs);
      console.log(`  moved: ${rel} -> ${posix(path.relative(projectRoot, destAbs))}`);
    }
    console.log('✅ Archive complete.');
    process.exitCode = 0;
  } else {
    console.log('✅ No orphaned CSS detected.');
  }
}

await main();
