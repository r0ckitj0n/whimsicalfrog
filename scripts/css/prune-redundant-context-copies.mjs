#!/usr/bin/env node
import fs from 'fs';
import fsp from 'fs/promises';
import path from 'path';
import postcss from 'postcss';

const projectRoot = path.resolve(path.join(path.dirname(new URL(import.meta.url).pathname), '../../'));
const stylesDir = path.join(projectRoot, 'src', 'styles');
const backupDir = path.join(projectRoot, 'backups', 'unused_styles', 'redundant-context-prune');

function timestamp() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}-${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
}

async function ensureDir(dir) {
  await fsp.mkdir(dir, { recursive: true });
}

function collectCssFiles(dir) {
  if (!fs.existsSync(dir)) return [];
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  const files = [];
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) files.push(...collectCssFiles(full));
    else if (entry.isFile() && full.endsWith('.css')) files.push(full);
  }
  return files;
}

function normalizeDecls(rule) {
  // Take last value per property
  const map = new Map();
  for (const node of rule.nodes || []) {
    if (node.type === 'decl') {
      const prop = String(node.prop || '').trim();
      const value = String(node.value || '').trim();
      map.set(prop, value);
    }
  }
  const entries = Array.from(map.entries()).sort((a, b) => a[0].localeCompare(b[0]));
  return JSON.stringify(entries);
}

function isInKeyframes(rule) {
  let p = rule.parent;
  while (p && p.type !== 'root') {
    if (p.type === 'atrule') {
      const name = String(p.name || '').toLowerCase();
      if (name === 'keyframes' || name.endsWith('keyframes')) return true;
    }
    p = p.parent;
  }
  return false;
}

function hasAtRuleContext(rule) {
  let p = rule.parent;
  while (p && p.type !== 'root') {
    if (p.type === 'atrule') return true;
    p = p.parent;
  }
  return false;
}

function pruneTree(root) {
  // Remove empty rules
  root.walkRules((r) => {
    const hasDecls = Array.isArray(r.nodes) && r.nodes.some((n) => n.type === 'decl');
    if (!hasDecls) r.remove();
  });
  // Remove empty blockful at-rules; preserve blockless
  root.walkAtRules((at) => {
    const hasBlock = Array.isArray(at.nodes);
    if (!hasBlock) return; // blockless -> keep
    const hasChildren = at.nodes && at.nodes.length > 0;
    if (!hasChildren) at.remove();
  });
}

async function readFile(abs) {
  try { return await fsp.readFile(abs, 'utf8'); } catch { return ''; }
}

async function main() {
  const files = collectCssFiles(stylesDir);
  const globalMapsByFile = new Map();

  // First pass: build global rule map per file: selector -> normalized decls
  for (const abs of files) {
    const css = await readFile(abs);
    if (!css) continue;
    const root = postcss.parse(css, { from: abs });
    const map = new Map();
    root.walkRules((rule) => {
      if (isInKeyframes(rule)) return;
      if (hasAtRuleContext(rule)) return; // only global
      const key = rule.selector.trim();
      const norm = normalizeDecls(rule);
      if (norm === '[]') return; // empty
      // keep the last occurrence (last-wins)
      map.set(key, norm);
    });
    globalMapsByFile.set(abs, map);
  }

  // Second pass: remove redundant at-rule copies identical to global rules
  const removedByFile = new Map(); // abs -> [cssBlock]
  const filesWritten = [];
  let removedCount = 0;

  for (const abs of files) {
    const css = await readFile(abs);
    if (!css) continue;
    const root = postcss.parse(css, { from: abs });
    const globals = globalMapsByFile.get(abs) || new Map();

    root.walkRules((rule) => {
      if (isInKeyframes(rule)) return;
      if (!hasAtRuleContext(rule)) return; // only consider at-rule context rules
      const key = rule.selector.trim();
      const norm = normalizeDecls(rule);
      if (norm === '[]') return;
      const globalNorm = globals.get(key);
      if (!globalNorm) return;
      if (globalNorm === norm) {
        // Archive removed block with context chain
        const chain = [];
        let p = rule.parent;
        while (p && p.type !== 'root') {
          if (p.type === 'atrule') chain.push(`@${p.name} ${p.params}`.trim());
          p = p.parent;
        }
        const contextStr = chain.reverse().join(' > ');
        const blockCss = contextStr ? `${contextStr} {\n${rule.toString()}\n}` : rule.toString();
        if (!removedByFile.has(abs)) removedByFile.set(abs, []);
        removedByFile.get(abs).push(blockCss);
        rule.remove();
        removedCount++;
      }
    });

    pruneTree(root);
    const out = root.toString();
    if (out !== css) {
      await fsp.writeFile(abs, out, 'utf8');
      filesWritten.push(path.relative(projectRoot, abs));
    }
  }

  // Write backups
  if (removedByFile.size > 0) {
    await ensureDir(backupDir);
    const ts = timestamp();
    for (const [abs, blocks] of removedByFile.entries()) {
      const rel = path.relative(projectRoot, abs);
      const base = path.basename(rel).replace(/\.css$/, '');
      const dest = path.join(backupDir, `${base}.pruned-${ts}.css`);
      await fsp.writeFile(dest, blocks.join('\n\n') + '\n', 'utf8');
      console.log(`  archived pruned blocks -> ${path.relative(projectRoot, dest)}`);
    }
  }

  console.log('\nPrune summary:');
  console.log(`  Files written: ${filesWritten.length}`);
  console.log(`  Rules removed: ${removedCount}`);
}

main().catch((err) => {
  console.error('Error during prune:', err);
  process.exit(1);
});
