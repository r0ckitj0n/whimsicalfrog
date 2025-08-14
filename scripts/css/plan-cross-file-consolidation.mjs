#!/usr/bin/env node
// Cross-file CSS Consolidation Plan Generator
// - Consumes reports/css-cross-file-duplicates.json
// - Computes a safe plan to consolidate duplicates while preserving last-wins cascade
// - Heuristics:
//   * Determine cascade order using main.css @import graph and JS/TS CSS imports
//   * Prefer canonical file as the last-wins file by cascade order
//   * For identical groups: remove from non-canonical files only
//   * For conflicting groups: compute merged declarations (last-wins) and remove others
// - Outputs: reports/css-cross-file-consolidation-plan.json

import { fileURLToPath } from 'url';
import path from 'path';
import fsp from 'fs/promises';
import { glob } from 'glob';
import postcss from 'postcss';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..', '..'); // scripts/css -> project root

const posix = (p) => p.split(path.sep).join('/');

// --- Arg parsing ---
function parseArgs(argv) {
  const out = { canonicalMapPath: null };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--canonical-map' && i + 1 < argv.length) {
      out.canonicalMapPath = argv[++i];
    } else if (a.startsWith('--canonical-map=')) {
      out.canonicalMapPath = a.slice('--canonical-map='.length);
    }
  }
  return out;
}

// --- Utilities ---
async function readFileSafe(abs) {
  try { return await fsp.readFile(abs, 'utf8'); } catch { return ''; }
}

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
  return chain.reverse().join(' > ');
}

function extractImportSpecFromAtRuleParams(params) {
  // Handle: @import "a.css";  @import url("a.css");  @import url(a.css);
  const s = String(params || '').trim();
  // url("...") or url('...')
  let m = s.match(/^url\(\s*["']([^"']+\.css)["']\s*\)/i);
  if (m) return m[1];
  m = s.match(/^url\(\s*([^\s)]+\.css)\s*\)/i);
  if (m) return m[1];
  // plain quotes
  m = s.match(/^["']([^"']+\.css)["']/);
  if (m) return m[1];
  return null;
}

function resolveImport(fromFileAbs, importSpec) {
  if (!importSpec || !importSpec.endsWith('.css')) return null;
  const baseDir = path.dirname(fromFileAbs);
  const resolved = path.resolve(baseDir, importSpec);
  const rel = posix(path.relative(projectRoot, resolved));
  return rel.startsWith('..') ? null : rel;
}

async function collectJsCssSeeds() {
  const files = await glob(['src/**/*.js', 'src/**/*.ts'], {
    cwd: projectRoot, nodir: true,
    ignore: ['**/node_modules/**', '**/dist/**', '**/backups/**']
  });
  const seeds = new Set();
  const importRe = /import\s+(?:[^'";]*from\s+)?["']([^"']+?\.css)["']/g;
  for (const rel of files) {
    const abs = path.join(projectRoot, rel);
    const src = await readFileSafe(abs);
    if (!src) continue;
    let m;
    while ((m = importRe.exec(src)) !== null) {
      const spec = m[1];
      const resolvedRel = resolveImport(abs, spec);
      if (resolvedRel) seeds.add(resolvedRel);
    }
  }
  return seeds;
}

async function buildCssImportOrder() {
  const visited = new Set();
  const order = [];

  async function process(rel) {
    if (!rel || visited.has(rel)) return;
    visited.add(rel);
    order.push(rel);

    const abs = path.join(projectRoot, rel);
    const css = await readFileSafe(abs);
    if (!css) return;

    let root;
    try { root = postcss.parse(css, { from: abs }); }
    catch { return; }

    // Walk @import in document order
    root.walkAtRules('import', (at) => {
      const spec = extractImportSpecFromAtRuleParams(at.params);
      const resolved = resolveImport(abs, spec);
      if (resolved) pending.push(resolved);
    });
  }

  const pending = [];
  // Seed with main.css if present
  const mainRel = 'src/styles/main.css';
  try {
    await fsp.access(path.join(projectRoot, mainRel));
    pending.push(mainRel);
  } catch {}

  // Also seed with JS/TS CSS imports
  const jsSeeds = await collectJsCssSeeds();
  for (const s of jsSeeds) pending.push(s);

  while (pending.length) {
    const next = pending.shift();
    await process(next);
  }

  // Build index map
  const idx = new Map();
  order.forEach((rel, i) => idx.set(rel, i));
  return idx; // file rel -> order index
}

function normalizeDecls(rule) {
  if (!Array.isArray(rule.nodes)) return null;
  for (const n of rule.nodes) if (n.type !== 'decl') return null;
  const index = new Map();
  for (const d of rule.nodes) {
    const prop = String(d.prop || '').trim();
    const value = String(d.value || '').trim();
    const important = !!d.important;
    index.set(prop, { prop, value, important });
  }
  return Array.from(index.values()); // last-wins inside rule
}

function declsToCss(selector, context, decls) {
  const body = decls
    .sort((a, b) => a.prop.localeCompare(b.prop))
    .map((d) => `  ${d.prop}: ${d.value}${d.important ? ' !important' : ''};`)
    .join('\n');
  const ruleCss = `${selector} {\n${body}\n}`;
  if (!context) return ruleCss;
  const parts = context.split(' > ');
  // Wrap rule in nested at-rules preserving order
  return parts.reduceRight((acc, at) => {
    return `${at} {\n${acc}\n}`;
  }, ruleCss);
}

async function findMatchingRules(rel, selector, context) {
  const abs = path.join(projectRoot, rel);
  const css = await readFileSafe(abs);
  if (!css) return [];
  let root;
  try { root = postcss.parse(css, { from: abs }); }
  catch { return [];
  }
  const matches = [];
  let localIndex = 0;
  root.walkRules((r) => {
    localIndex++;
    if (r.selector !== selector) return;
    const ctx = getAtRuleContext(r);
    if (ctx !== (context || '')) return;
    const decls = normalizeDecls(r);
    if (!decls) return;
    matches.push({ decls, localIndex });
  });
  return matches; // in document order
}

function chooseCanonical(files, orderIdx) {
  // Prefer the one with the highest cascade order index; fallback to components/ preference
  let best = null;
  let bestOrder = -1;
  for (const f of files) {
    const ord = orderIdx.has(f) ? orderIdx.get(f) : -1;
    if (ord > bestOrder) { best = f; bestOrder = ord; }
  }
  if (best) return best;
  // Fallback heuristic
  const comp = files.find((f) => f.includes('src/styles/components/'));
  if (comp) return comp;
  return files[0];
}

function matchOverrideRule(selector, context, rule) {
  if (rule.context != null && String(rule.context || '') !== String(context || '')) return false;
  if (rule.selector && selector === rule.selector) return true;
  if (rule.selectorPrefix && selector.startsWith(rule.selectorPrefix)) return true;
  if (rule.selectorRegex) {
    try {
      const re = new RegExp(rule.selectorRegex);
      if (re.test(selector)) return true;
    } catch {}
  }
  return false;
}

function chooseCanonicalWithOverrides(files, orderIdx, selector, context, overrides) {
  if (overrides && Array.isArray(overrides.rules)) {
    for (const rule of overrides.rules) {
      if (!rule || !rule.canonical) continue;
      if (matchOverrideRule(selector, context, rule)) {
        const canon = posix(rule.canonical);
        if (files.includes(canon)) return canon;
        // If the preferred canonical isn't among the files for this group, fall back to default heuristic
        break;
      }
    }
  }
  return chooseCanonical(files, orderIdx);
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  let canonicalOverrides = null;
  if (args.canonicalMapPath) {
    const mapAbs = path.isAbsolute(args.canonicalMapPath)
      ? args.canonicalMapPath
      : path.join(projectRoot, args.canonicalMapPath);
    try {
      const raw = await readFileSafe(mapAbs);
      if (raw) canonicalOverrides = JSON.parse(raw);
    } catch {}
  }
  const reportPath = path.join(projectRoot, 'reports', 'css-cross-file-duplicates.json');
  const raw = await readFileSafe(reportPath);
  if (!raw) {
    console.error('Report not found:', posix(path.relative(projectRoot, reportPath)));
    process.exit(1);
  }
  const report = JSON.parse(raw);

  const orderIdx = await buildCssImportOrder();

  const plan = {
    generatedAt: new Date().toISOString(),
    baseDir: 'src/styles/',
    filesConsidered: report.filesScanned,
    groupsConsidered: report.totalGroups,
    identicalGroups: 0,
    conflictingGroups: 0,
    items: []
  };

  for (const item of report.items) {
    const selector = item.selector;
    const context = item.context || '';
    const files = item.files;
    if (!files || files.length <= 1) continue;

    // Determine canonical target preserving behavior (last-wins by cascade order), with optional overrides
    const canonical = chooseCanonicalWithOverrides(files, orderIdx, selector, context, canonicalOverrides);

    // Re-collect all matching rules in cascade order: by file order, then local rule order
    const ranked = [];
    for (const f of files) {
      const ord = orderIdx.has(f) ? orderIdx.get(f) : -1;
      const rules = await findMatchingRules(f, selector, context);
      for (const r of rules) {
        ranked.push({ file: f, order: ord, localIndex: r.localIndex, decls: r.decls });
      }
    }
    ranked.sort((a, b) => (a.order - b.order) || (a.localIndex - b.localIndex));

    // Merge decls last-wins across all occurrences
    const mergedMap = new Map();
    for (const r of ranked) {
      for (const d of r.decls) mergedMap.set(d.prop, { ...d });
    }
    const mergedDecls = Array.from(mergedMap.values());

    // Determine kind: identical or conflicting w.r.t. signatures in input
    const kind = item.kind; // already classified

    const removeFrom = files.filter((f) => f !== canonical);
    const insertRule = declsToCss(selector, context, mergedDecls);

    plan.items.push({
      selector,
      context,
      kind,
      canonical,
      removeFrom,
      mergedDecls,
      ruleCss: insertRule
    });
    if (kind === 'identical') plan.identicalGroups++; else plan.conflictingGroups++;
  }

  const outDir = path.join(projectRoot, 'reports');
  await fsp.mkdir(outDir, { recursive: true });
  const outPath = path.join(outDir, 'css-cross-file-consolidation-plan.json');
  await fsp.writeFile(outPath, JSON.stringify(plan, null, 2), 'utf8');

  console.log('Consolidation plan written to', posix(path.relative(projectRoot, outPath)));
  console.log(`Groups: ${plan.items.length} (identical: ${plan.identicalGroups}, conflicting: ${plan.conflictingGroups})`);
}

await main();
