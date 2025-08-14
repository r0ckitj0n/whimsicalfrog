#!/usr/bin/env node
// Cross-file CSS Duplicate Selector Reporter
// - Scans src/styles/**/*.css
// - Groups rules by (at-rule context chain, selector)
// - Only considers flat declaration rules (skips nested/complex)
// - Compares declaration sets across files using a normalized signature
// - Outputs JSON report to reports/css-cross-file-duplicates.json
import { fileURLToPath } from 'url';
import path from 'path';
import fsp from 'fs/promises';
import { glob } from 'glob';
import postcss from 'postcss';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..', '..'); // scripts/css -> project root

const posix = (p) => p.split(path.sep).join('/');

function getAtRuleContext(node) {
  const chain = [];
  let p = node.parent;
  while (p && p.type !== 'root') {
    if (p.type === 'atrule') {
      const name = `@${p.name}`.trim();
      const params = String(p.params || '').trim();
      chain.push(params ? `${name} ${params}` : name);
    }
    p = p.parent;
  }
  return chain.reverse().join(' > '); // innermost last
}

function normalizeDecls(rule) {
  if (!Array.isArray(rule.nodes)) return null;
  // Ensure rule is flat: allow decls and comments only (skip nested rules safely)
  for (const n of rule.nodes) {
    if (n.type !== 'decl' && n.type !== 'comment') return null;
  }
  // Last-wins per property within the rule, then alphabetize properties for a stable signature
  const index = new Map(); // prop -> {prop, value, important}
  for (const d of rule.nodes) {
    if (d.type !== 'decl') continue; // ignore comments in signature
    const prop = String(d.prop || '').trim();
    const value = String(d.value || '').trim();
    const important = !!d.important;
    index.set(prop, { prop, value, important });
  }
  const canonical = Array.from(index.values()).sort((a, b) => a.prop.localeCompare(b.prop));
  const signature = canonical
    .map((d) => `${d.prop}:${d.value}${d.important ? ' !important' : ''};`)
    .join('');
  return { canonical, signature };
}

async function ensureDir(dir) {
  await fsp.mkdir(dir, { recursive: true });
}

async function main() {
  const cssFiles = await glob('src/styles/**/*.css', {
    cwd: projectRoot,
    nodir: true,
    ignore: ['**/node_modules/**', '**/dist/**', '**/backups/**']
  });
  if (!cssFiles.length) {
    console.log('No CSS files found under src/styles/.');
    return;
  }

  const groups = new Map(); // key: `${ctx}||${selector}` -> array of {file, signature, decls}
  let parsedCount = 0;

  for (const rel of cssFiles) {
    const abs = path.join(projectRoot, rel);
    const css = await fsp.readFile(abs, 'utf8');
    let root;
    try {
      root = postcss.parse(css, { from: abs });
    } catch (e) {
      console.warn(`Parse error in ${rel}: ${e.message}`);
      continue;
    }
    parsedCount++;

    root.walkRules((r) => {
      // Skip rules within @keyframes (and vendor variants)
      let p = r.parent;
      while (p && p.type !== 'root') {
        if (p.type === 'atrule') {
          const name = String(p.name || '').toLowerCase();
          if (name === 'keyframes' || name.endsWith('keyframes')) return; // ignore keyframe steps
        }
        p = p.parent;
      }
      const norm = normalizeDecls(r);
      if (!norm) return; // skip complex rules
      const ctx = getAtRuleContext(r);
      const key = `${ctx}||${r.selector}`;
      const arr = groups.get(key) || [];
      arr.push({ file: posix(rel), signature: norm.signature, decls: norm.canonical });
      groups.set(key, arr);
    });
  }

  // Build report: only groups that span more than one distinct file
  const report = {
    generatedAt: new Date().toISOString(),
    baseDir: 'src/styles/',
    filesScanned: parsedCount,
    totalGroups: 0,
    crossFileGroups: 0,
    identicalGroups: 0,
    conflictingGroups: 0,
    items: []
  };

  for (const [key, arr] of groups.entries()) {
    const distinctFiles = Array.from(new Set(arr.map((e) => e.file)));
    if (distinctFiles.length <= 1) continue; // not cross-file

    const [ctx, selector] = key.split('||');
    const sigSet = new Map(); // signature -> files[]
    for (const e of arr) {
      const files = sigSet.get(e.signature) || new Set();
      files.add(e.file);
      sigSet.set(e.signature, files);
    }
    const uniqueSignatures = sigSet.size;
    const kind = uniqueSignatures === 1 ? 'identical' : 'conflicting';

    const signatureExamples = {};
    for (const [sig, files] of sigSet.entries()) {
      signatureExamples[sig] = Array.from(files.values()).sort();
    }

    report.items.push({
      selector,
      context: ctx || '',
      files: distinctFiles.sort(),
      uniqueSignatures,
      kind,
      signatureExamples
    });
    report.crossFileGroups++;
    if (kind === 'identical') report.identicalGroups++;
    else report.conflictingGroups++;
  }

  // Sort items for readability: conflicting first, then by selector
  report.items.sort((a, b) => {
    if (a.kind !== b.kind) return a.kind === 'conflicting' ? -1 : 1;
    if (a.selector !== b.selector) return a.selector.localeCompare(b.selector);
    return (a.context || '').localeCompare(b.context || '');
  });
  report.totalGroups = report.items.length;

  // Write report
  const outDir = path.join(projectRoot, 'reports');
  await ensureDir(outDir);
  const outPath = path.join(outDir, 'css-cross-file-duplicates.json');
  await fsp.writeFile(outPath, JSON.stringify(report, null, 2), 'utf8');

  console.log('Cross-file duplicate selector report written to', posix(path.relative(projectRoot, outPath)));
  console.log(`Files scanned: ${report.filesScanned}`);
  console.log(`Cross-file groups: ${report.crossFileGroups}`);
  console.log(` - identical: ${report.identicalGroups}`);
  console.log(` - conflicting: ${report.conflictingGroups}`);
}

await main();
