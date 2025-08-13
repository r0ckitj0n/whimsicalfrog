#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import * as recast from 'recast';
import babelParser from 'recast/parsers/babel.js';

const b = recast.types.builders;

function ensureDir(p) {
  fs.mkdirSync(p, { recursive: true });
}

function readJson(p) {
  return JSON.parse(fs.readFileSync(p, 'utf8'));
}

function writeFile(p, content) {
  ensureDir(path.dirname(p));
  fs.writeFileSync(p, content, 'utf8');
}

function posInNode(pos, node) {
  if (!node.loc) return false;
  const { start, end } = node.loc;
  const afterStart = (pos.line > start.line) || (pos.line === start.line && pos.column >= start.column);
  const beforeEnd = (pos.line < end.line) || (pos.line === end.line && pos.column <= end.column);
  return afterStart && beforeEnd;
}

function findSmallestNodeAt(ast, pos) {
  let found = null;
  recast.types.visit(ast, {
    visitIdentifier(path) {
      const n = path.node;
      if (posInNode(pos, n)) {
        if (!found || (n.loc && found.loc && (n.loc.end.line - n.loc.start.line <= found.loc.end.line - found.loc.start.line))) {
          found = path;
        }
      }
      return this.traverse(path);
    }
  });
  return found;
}

function isShorthandObjectProperty(path) {
  const n = path.node;
  const parent = path.parent && path.parent.node;
  return parent && parent.type === 'ObjectProperty' && parent.shorthand === true && parent.value === n;
}

function expandShorthandProperty(parentPath, newId) {
  // parentPath is the ObjectProperty path
  const pNode = parentPath.node;
  pNode.shorthand = false;
  pNode.value = newId;
}

function prefixName(name) {
  if (name.startsWith('_')) return name;
  return `_${name}`;
}

function modifyIdentifierAt(ast, pos) {
  const idPath = findSmallestNodeAt(ast, pos);
  if (!idPath) return { changed: false, reason: 'node-not-found' };
  const n = idPath.node;

  // We expect Identifier or BindingIdentifier
  if (n.type !== 'Identifier') {
    return { changed: false, reason: `node-not-identifier:${n.type}` };
  }
  if (n.name.startsWith('_')) {
    return { changed: false, reason: 'already-prefixed' };
  }

  // If part of shorthand object pattern/property, expand to non-shorthand
  const parentPath = idPath.parentPath || idPath.parent; // recast uses parentPath
  const parent = parentPath && parentPath.node;
  const grandPath = parentPath && parentPath.parentPath;
  const grand = grandPath && grandPath.node;

  if (parent) {
    // Handle function declaration/expressions names
    if ((parent.type === 'FunctionDeclaration' || parent.type === 'FunctionExpression' || parent.type === 'ClassDeclaration') && parent.id === n) {
      parent.id.name = prefixName(parent.id.name);
      return { changed: true };
    }

    // Parameter identifiers (Function params, ArrowFunctionExpression)
    // When traversing, the Identifier's immediate parentPath is typically the array of params; so the function node is grandPath.node
    const funcLikeTypes = new Set(['FunctionDeclaration','FunctionExpression','ArrowFunctionExpression','ObjectMethod','ClassMethod']);
    if (
      (funcLikeTypes.has(parent.type) && parent.params && parent.params.includes(n)) ||
      (grand && funcLikeTypes.has(grand.type) && parentPath.name === 'params')
    ) {
      n.name = prefixName(n.name);
      return { changed: true };
    }

    // VariableDeclarator id
    if (parent.type === 'VariableDeclarator' && parent.id === n) {
      n.name = prefixName(n.name);
      return { changed: true };
    }

    // CatchClause param (we typically ignore, but ESLint config uses caughtErrors: none)
    if (parent.type === 'CatchClause' && parent.param === n) {
      // skip per config; should not be reported
      return { changed: false, reason: 'catch-param-skip' };
    }

    // ObjectPattern property shorthand: { a } -> { a: _a }
    if (parent.type === 'ObjectProperty') {
      // If Identifier is the value in a shorthand property within an ObjectPattern
      const g = grand;
      if (g && g.type === 'ObjectPattern') {
        if (parent.shorthand === true && parent.value === n && parent.key.type === 'Identifier' && parent.key.name === n.name) {
          // expand shorthand
          parent.shorthand = false;
          parent.value = b.identifier(prefixName(n.name));
          return { changed: true };
        }
        // Non-shorthand value identifier inside ObjectPattern: just prefix value name
        if (parent.value === n) {
          n.name = prefixName(n.name);
          return { changed: true };
        }
      }
      // Shorthand property in object literal (not pattern) e.g., const o = { a }; -> usually not a binding; avoid touching
    }

    // ArrayPattern element: [a] -> [_a]
    const gp = grand;
    if (gp && gp.type === 'ArrayPattern') {
      n.name = prefixName(n.name);
      return { changed: true };
    }
  }

  // Fallback: just prefix identifier
  n.name = prefixName(n.name);
  return { changed: true };
}

function processFile(filePath, positions) {
  const src = fs.readFileSync(filePath, 'utf8');
  const ast = recast.parse(src, { parser: babelParser });

  let changed = false;
  const results = [];

  positions.forEach((pos) => {
    const r = modifyIdentifierAt(ast, pos);
    results.push({ pos, ...r });
    if (r.changed) changed = true;
  });

  if (!changed) return { changed: false, results };

  const out = recast.print(ast).code;
  return { changed: true, results, out };
}

function runESLintJSON(outFile, patterns) {
  ensureDir(path.dirname(outFile));
  const args = [
    '--cache',
    '--cache-location', '.cache/eslint/',
    ...patterns,
    '-f', 'json', '-o', outFile
  ];
  const eslintJs = path.resolve('node_modules/eslint/bin/eslint.js');
  const res = spawnSync(process.execPath, [eslintJs, ...args], { stdio: 'inherit' });
  if (res.error || res.status !== 0) {
    // eslint exits with non-zero on lint errors; that's expected. Only treat spawn error as fatal.
    if (res.error) {
      throw res.error;
    }
  }
}

function main() {
  const args = new Set(process.argv.slice(2));
  const getArgVal = (name, def = undefined) => {
    for (let i = 2; i < process.argv.length; i++) {
      if (process.argv[i] === name) return process.argv[i + 1];
    }
    return def;
  };

  const reportPath = getArgVal('--report', 'logs/eslint-report.json');
  const write = args.has('--write');
  const runLint = args.has('--run-eslint');

  // Collect positional patterns (files/globs) after filtering out known flags
  const positional = [];
  for (let i = 2; i < process.argv.length; i++) {
    const a = process.argv[i];
    if (a === '--report') { i++; continue; }
    if (a === '--write' || a === '--run-eslint') continue;
    if (a.startsWith('--')) continue;
    positional.push(a);
  }

  const patterns = positional.length > 0 ? positional : [
    'src/**/*.js',
    'scripts/**/*.js',
    'scripts/**/*.cjs'
  ];

  if (runLint) {
    console.log('Running ESLint to generate JSON report...');
    runESLintJSON(reportPath, patterns);
  }

  if (!fs.existsSync(reportPath)) {
    console.error(`ESLint report not found at ${reportPath}. Run with --run-eslint or provide --report <path>.`);
    process.exit(2);
  }

  const report = readJson(reportPath);

  // Map file -> positions to change
  const fileMap = new Map();

  for (const file of report) {
    const filePath = file.filePath;
    for (const m of file.messages) {
      if (m.ruleId !== 'no-unused-vars') continue;
      // m.line, m.column are 1-based; recast loc columns are 0-based for column
      const pos = { line: m.line, column: Math.max(0, (m.column || 1) - 1) };
      if (!fileMap.has(filePath)) fileMap.set(filePath, []);
      fileMap.get(filePath).push(pos);
    }
  }

  if (fileMap.size === 0) {
    console.log('No no-unused-vars issues found. Nothing to do.');
    process.exit(0);
  }

  const summary = [];

  for (const [filePath, positions] of fileMap.entries()) {
    const rel = path.relative(process.cwd(), filePath);
    try {
      const result = processFile(filePath, positions);
      if (result.changed) {
        summary.push({ file: rel, changes: result.results.filter(r => r.changed).length });
        if (write && result.out) {
          writeFile(filePath, result.out);
        }
      } else {
        summary.push({ file: rel, changes: 0 });
      }
    } catch (e) {
      console.error(`Error processing ${rel}:`, e.message);
      summary.push({ file: rel, error: e.message });
    }
  }

  console.log((write ? 'Applied' : 'Planned') + ' underscore prefixing for unused identifiers:');
  for (const s of summary) {
    if (s.error) {
      console.log(` - ${s.file}: ERROR ${s.error}`);
    } else {
      console.log(` - ${s.file}: ${s.changes} change(s)`);
    }
  }

  if (!write) {
    console.log('\nDry run complete. Re-run with --write to apply changes.');
  }
}

main();
