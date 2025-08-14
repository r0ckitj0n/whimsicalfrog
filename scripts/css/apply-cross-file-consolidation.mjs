#!/usr/bin/env node
// Apply cross-file CSS consolidation based on plan JSON
// Default: apply only identical groups (safe). Use --all to include conflicting groups.

import { fileURLToPath } from 'url';
import path from 'path';
import fsp from 'fs/promises';
import postcss from 'postcss';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..', '..');

const posix = (p) => p.split(path.sep).join('/');
const hasFlag = (flag) => process.argv.includes(flag);
const APPLY_ALL = hasFlag('--all');

async function ensureDir(dir) {
  await fsp.mkdir(dir, { recursive: true });
}

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

function isFlatDeclRule(rule) {
  if (!Array.isArray(rule.nodes)) return false;
  return rule.nodes.every((n) => n.type === 'decl' || n.type === 'comment');
}

function declsToCss(selector, context, decls) {
  const body = decls
    .sort((a, b) => a.prop.localeCompare(b.prop))
    .map((d) => `  ${d.prop}: ${d.value}${d.important ? ' !important' : ''};`)
    .join('\n');
  const ruleCss = `${selector} {\n${body}\n}`;
  if (!context) return ruleCss;
  const parts = context.split(' > ');
  return parts.reduceRight((acc, at) => `${at} {\n${acc}\n}`, ruleCss);
}

function pruneTree(root) {
  // Remove empty rules
  root.walkRules((r) => {
    const hasDecls = Array.isArray(r.nodes) && r.nodes.some((n) => n.type === 'decl');
    if (!hasDecls) r.remove();
  });
  // Remove empty blockful at-rules; preserve blockless (@import/@charset/@namespace/@layer without block)
  root.walkAtRules((at) => {
    const hasBlock = Array.isArray(at.nodes);
    if (!hasBlock) return; // blockless -> keep
    const hasChildren = at.nodes && at.nodes.length > 0;
    if (!hasChildren) at.remove();
  });
}

async function loadOrInitFile(rel) {
  const abs = path.join(projectRoot, rel);
  const css = await readFileSafe(abs);
  if (!css) {
    return { rel, abs, root: postcss.root(), removed: [], inserted: [] };
  }
  let root;
  try { root = postcss.parse(css, { from: abs }); }
  catch (e) {
    console.warn(`Parse error in ${rel}: ${e.message}`);
    root = postcss.root();
  }
  return { rel, abs, root, removed: [], inserted: [] };
}

function archiveEntryHeader(rel) {
  const now = new Date().toISOString();
  return `/* Removed from ${rel} on ${now} */\n`;
}

async function writeBackups(removedByFile) {
  if (!removedByFile.size) return;
  const backupRoot = path.join(projectRoot, 'backups', 'unused_styles', 'removed-rules');
  await ensureDir(backupRoot);
  const ts = new Date().toISOString().replace(/[:.]/g, '').replace('T', '-').slice(0, 17);
  for (const [rel, snippets] of removedByFile.entries()) {
    if (!snippets.length) continue;
    const relFromStyles = rel.startsWith('src/styles/') ? rel.slice('src/styles/'.length) : path.basename(rel);
    const destDir = path.join(backupRoot, path.dirname(relFromStyles));
    await ensureDir(destDir);
    const base = path.basename(relFromStyles).replace(/\.css$/i, '');
    const outPath = path.join(destDir, `${base}.removed-${ts}.css`);
    const header = archiveEntryHeader(rel);
    await fsp.writeFile(outPath, header + snippets.join('\n\n') + '\n', 'utf8');
    console.log('  archived removed blocks ->', posix(path.relative(projectRoot, outPath)));
  }
}

async function main() {
  const planPath = path.join(projectRoot, 'reports', 'css-cross-file-consolidation-plan.json');
  const raw = await readFileSafe(planPath);
  if (!raw) {
    console.error('Consolidation plan not found:', posix(path.relative(projectRoot, planPath)));
    process.exit(1);
  }
  const plan = JSON.parse(raw);

  const fileMap = new Map(); // rel -> {rel, abs, root, removed[], inserted[]}
  const removedByFile = new Map(); // rel -> [cssSnippet]

  let groupsProcessed = 0;
  let rulesRemoved = 0;
  let rulesInserted = 0;

  for (const item of plan.items) {
    const { selector, context, kind, canonical, removeFrom, mergedDecls, ruleCss } = item;
    if (!APPLY_ALL && kind !== 'identical') continue;
    groupsProcessed++;

    // Remove from non-canonical files
    for (const rel of removeFrom) {
      const rec = fileMap.get(rel) || await loadOrInitFile(rel);
      if (!fileMap.has(rel)) fileMap.set(rel, rec);
      const { root } = rec;
      const toRemove = [];
      root.walkRules((r) => {
        if (r.selector !== selector) return;
        if (getAtRuleContext(r) !== (context || '')) return;
        if (!isFlatDeclRule(r)) return;
        toRemove.push(r);
      });
      if (toRemove.length) {
        const snippets = removedByFile.get(rel) || [];
        for (const r of toRemove) {
          snippets.push(r.toString());
          r.remove();
          rulesRemoved++;
        }
        removedByFile.set(rel, snippets);
      }
    }

    // Ensure canonical has one occurrence
    const canRec = fileMap.get(canonical) || await loadOrInitFile(canonical);
    if (!fileMap.has(canonical)) fileMap.set(canonical, canRec);
    let hasRule = false;
    canRec.root.walkRules((r) => {
      if (hasRule) return;
      if (r.selector !== selector) return;
      if (getAtRuleContext(r) !== (context || '')) return;
      if (!isFlatDeclRule(r)) return;
      hasRule = true;
    });
    if (!hasRule) {
      // Insert at end of file
      const cssToInsert = declsToCss(selector, context || '', mergedDecls);
      canRec.root.append(postcss.parse(cssToInsert));
      canRec.inserted.push({ selector, context });
      rulesInserted++;
    }
  }

  // Write backups then files
  await writeBackups(removedByFile);

  const filesWritten = [];
  for (const [rel, rec] of fileMap.entries()) {
    // Prune empties before writing to satisfy stylelint block-no-empty
    pruneTree(rec.root);
    const outCss = rec.root.toString();
    await fsp.writeFile(rec.abs, outCss, 'utf8');
    filesWritten.push(rel);
  }

  console.log('\nApply summary:');
  console.log('  Groups processed:', groupsProcessed);
  console.log('  Files written:', filesWritten.length);
  console.log('  Rules removed:', rulesRemoved);
  console.log('  Rules inserted (canonical):', rulesInserted);
}

await main();
