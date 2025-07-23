#!/usr/bin/env node
/**
 * Clean automated splitter:
 *  • JS: split >15 KB files by top-level declarations (recast).
 *  • CSS: only room-, popup-, and global- prefixed files; skip >200 KB or bundles; naive regex split on comment headers.
 */

const fs = require('fs');
const path = require('path');
const recast = require('recast');

const PROJECT = path.resolve(__dirname, '..');
const JS_DIR = path.join(PROJECT, 'js');
const CSS_DIR = path.join(PROJECT, 'css');
const LEGACY = path.join(PROJECT, 'legacy_bigfiles');
const JS_THRESHOLD = 15 * 1024; // 15KB
const CSS_TARGET_RE = /\b(room-|popup-|global-)/i;

// helpers
const ensure = d => fs.existsSync(d) || fs.mkdirSync(d, { recursive: true });
const backup = file => {
  ensure(LEGACY);
  const rel = path.relative(PROJECT, file);
  const dest = path.join(LEGACY, rel);
  ensure(path.dirname(dest));
  fs.copyFileSync(file, dest);
};

// JS splitter
function splitJS(file) {
  const code = fs.readFileSync(file, 'utf8');
  if (code.length < JS_THRESHOLD) return;

  console.log('[JS] split', path.relative(PROJECT, file));
  backup(file);

  const ast = recast.parse(code, { parser: require('recast/parsers/babel') });
  const pieces = [];
  recast.types.visit(ast, {
    visitFunctionDeclaration(p) {
      pieces.push({ n: p.node.id.name, c: recast.print(p.node).code });
      return false;
    },
    visitClassDeclaration(p) {
      pieces.push({ n: p.node.id.name, c: recast.print(p.node).code });
      return false;
    },
    visitVariableDeclaration(p) {
      if (p.parentPath.parentPath.node.type === 'Program') {
        pieces.push({ n: p.node.declarations[0].id.name, c: recast.print(p.node).code });
      }
      return false;
    }
  });
  if (!pieces.length) return;

  const base = path.basename(file, '.js');
  const dir = path.join(JS_DIR, base);
  ensure(dir);
  pieces.forEach(p => fs.writeFileSync(path.join(dir, `${p.n}.js`), p.c + '\n'));
  fs.writeFileSync(path.join(dir, 'index.js'), pieces.map(p => `export * from './${p.n}.js';`).join('\n'));
  fs.writeFileSync(file, `// barrel after auto-split\nexport * from './${base}/index.js';\n`);
}

// CSS splitter (naive)
function splitCSS(file) {
  const base = path.basename(file);
  const size = fs.statSync(file).size;
  if (!CSS_TARGET_RE.test(base) || base.includes('bundle') || size > 200 * 1024) return;

  console.log('[CSS] split', path.relative(PROJECT, file));
  backup(file);

  const css = fs.readFileSync(file, 'utf8');
  // split on comment headers like /* ===== something */
  const chunks = css.split(/\n\s*\/\*[^!]/).filter(Boolean);
  if (chunks.length <= 1) return;

  const dir = path.join(CSS_DIR, base.replace('.css', ''));
  ensure(dir);
  chunks.forEach((chunk, i) => {
    fs.writeFileSync(path.join(dir, `chunk-${i}.css`), '/* auto-chunk */\n/*' + chunk);
  });
  fs.writeFileSync(file, '/* auto imports */\n' + chunks.map((_, i) => `@import './${base.replace('.css','')}/chunk-${i}.css';`).join('\n') + '\n');
}

function walk(dir, ext, cb) {
  fs.readdirSync(dir, { withFileTypes: true }).forEach(ent => {
    const full = path.join(dir, ent.name);
    if (ent.isDirectory()) walk(full, ext, cb);
    else if (full.endsWith(ext)) cb(full);
  });
}

console.log('=== auto-split start ===');
walk(JS_DIR, '.js', splitJS);
walk(CSS_DIR, '.css', splitCSS);
console.log('=== auto-split done ===');
