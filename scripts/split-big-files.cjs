#!/usr/bin/env node
/**
 * CommonJS automated splitter for oversized JS & CSS files.
 * Identical logic to split-big-files.js but runnable via `node` in a type:module project.
 */

const fs = require('fs');
const path = require('path');
const recast = require('recast');
const postcss = require('postcss');
const selectorParser = require('postcss-selector-parser');

const PROJECT_ROOT = path.resolve(__dirname, '..');
const JS_DIR = path.join(PROJECT_ROOT, 'js');
const CSS_DIR = path.join(PROJECT_ROOT, 'css');
const LEGACY_DIR = path.join(PROJECT_ROOT, 'legacy_bigfiles');

const JS_THRESHOLD = 15 * 1024; // 15 KB

function ensureDir(dir) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

function backupFile(filePath) {
  ensureDir(LEGACY_DIR);
  const rel = path.relative(PROJECT_ROOT, filePath);
  const dest = path.join(LEGACY_DIR, rel);
  ensureDir(path.dirname(dest));
  fs.copyFileSync(filePath, dest);
}

function splitJS(file) {
  const code = fs.readFileSync(file, 'utf8');
  if (Buffer.byteLength(code) < JS_THRESHOLD) return;

  console.log('\n[JS] Splitting', path.relative(PROJECT_ROOT, file));
  backupFile(file);

  const ast = recast.parse(code, { parser: require('recast/parsers/babel') });
  const pieces = [];
  recast.types.visit(ast, {
    visitFunctionDeclaration(p) {
      pieces.push({ name: p.node.id.name, code: recast.print(p.node).code });
      return false;
    },
    visitClassDeclaration(p) {
      pieces.push({ name: p.node.id.name, code: recast.print(p.node).code });
      return false;
    },
    visitVariableDeclaration(p) {
      if (p.parentPath.parentPath.node.type === 'Program') {
        pieces.push({ name: p.node.declarations[0].id.name, code: recast.print(p.node).code });
      }
      return false;
    },
  });

  if (!pieces.length) return console.warn('   No top-level pieces found, skipped.');

  const baseName = path.basename(file, '.js');
  const featureDir = path.join(JS_DIR, baseName);
  ensureDir(featureDir);

  pieces.forEach(piece => {
    const out = path.join(featureDir, piece.name + '.js');
    fs.writeFileSync(out, piece.code + '\n');
  });
  fs.writeFileSync(path.join(featureDir, 'index.js'), pieces.map(p => `export * from './${p.name}.js';`).join('\n') + '\n');
  fs.writeFileSync(file, `// Auto-generated barrel after split\nexport * from './${baseName}/index.js';\n`);
}

function getSelectorPrefix(selector) {
  let prefix = '';
  selectorParser(root => {
    root.walkClasses(c => { prefix = c.value.split('-')[0]; return false; });
    root.walkTags(t => { if (!prefix) prefix = t.value.split('-')[0]; return false; });
  }).processSync(selector);
  return prefix || 'misc';
}

function splitCSS(file) {
  const css = fs.readFileSync(file, 'utf8');
  if (Buffer.byteLength(css) < JS_THRESHOLD) return;

  console.log('\n[CSS] Splitting', path.relative(PROJECT_ROOT, file));
  backupFile(file);

  const root = postcss.parse(css);
  const buckets = {};
  root.each(node => {
    if (node.type !== 'rule') return;
    const sel = node.selector.split(',')[0].trim();
    const prefix = getSelectorPrefix(sel);
    (buckets[prefix] = buckets[prefix] || []).push(node.toString());
  });

  const baseName = path.basename(file, '.css');
  const featureDir = path.join(CSS_DIR, baseName);
  ensureDir(featureDir);

  Object.entries(buckets).forEach(([prefix, rules]) => {
    fs.writeFileSync(path.join(featureDir, `${prefix}.css`), rules.join('\n') + '\n');
  });
  fs.writeFileSync(file, `/* Auto-generated imports after split */\n${Object.keys(buckets).map(p => `@import './${baseName}/${p}.css';`).join('\n')}\n`);
}

function walk(dir, ext, cb) {
  fs.readdirSync(dir, { withFileTypes: true }).forEach(ent => {
    const full = path.join(dir, ent.name);
    if (ent.isDirectory()) return walk(full, ext, cb);
    if (full.endsWith(ext)) cb(full);
  });
}

console.log('Starting automated split...');
walk(JS_DIR, '.js', splitJS);
walk(CSS_DIR, '.css', splitCSS);
console.log('\nSplit complete. Run your build to verify.');

