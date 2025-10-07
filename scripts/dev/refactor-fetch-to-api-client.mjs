#!/usr/bin/env node
/**
 * Codemod: Replace raw fetch() with ApiClient in target files.
 * - Transforms common patterns only (safe subset):
 *   1) await fetch(url, { method:'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
 *      -> await ApiClient.post(url, data)
 *   2) await fetch(url).then(r => r.json()) OR const res = await fetch(url); const data = await res.json();
 *      -> await ApiClient.get(url)
 *   3) await fetch(url, { method: 'DELETE' })
 *      -> await ApiClient.delete(url)
 *   4) await fetch(url, { method: 'POST', body: formData }) or with headers omitted
 *      -> await ApiClient.upload(url, formData)
 *   5) await fetch(url, { credentials: 'same-origin' })
 *      -> await ApiClient.get(url) (credentials managed by ApiClient)
 * - Skips ambiguous/text/blob responses.
 * - Ensures `import { ApiClient } from '../core/api-client.js'` (or proper relative) is present.
 */

import fs from 'node:fs';
import path from 'node:path';

const files = process.argv.slice(2);
if (!files.length) {
  console.error('Usage: node scripts/dev/refactor-fetch-to-api-client.mjs <file1> <file2> ...');
  process.exit(1);
}

function ensureImport(text, relToFile) {
  const importLine = `import { ApiClient } from '${relToFile}';`;
  if (text.includes(importLine)) return text;
  // If file already imports ApiClient (any path), keep it.
  if (/import\s+\{\s*ApiClient\s*}\s+from\s+['"].+api-client\.js['"];?/.test(text)) return text;
  // Insert after initial comments or imports
  const lines = text.split(/\r?\n/);
  let insertAt = 0;
  while (insertAt < lines.length && (/^\s*\/\//.test(lines[insertAt]) || /^\s*$/.test(lines[insertAt]) || /^\s*\/\*/.test(lines[insertAt]) || /^\s*import\s+/.test(lines[insertAt]))) {
    insertAt++;
  }
  lines.splice(insertAt, 0, importLine);
  return lines.join('\n');
}

function computeRelativeImport(filePath) {
  // Prefer importing from src/core/api-client.js with correct relative path
  const projectRoot = process.cwd();
  const target = path.join(projectRoot, 'src', 'core', 'api-client.js');
  let rel = path.relative(path.dirname(filePath), target).replace(/\\/g, '/');
  if (!rel.startsWith('.')) rel = './' + rel;
  return rel;
}

function transform(content) {
  let text = content;

  // Pattern 1: POST JSON with JSON.stringify
  text = text.replace(/await\s+fetch\(\s*([^,]+)\s*,\s*\{[^}]*?method\s*:\s*['"]POST['"][^}]*?body\s*:\s*JSON\.stringify\(([^)]+)\)[^}]*}\s*\)/gms,
    (m, urlExpr, dataExpr) => `await ApiClient.post(${urlExpr.trim()}, ${dataExpr.trim()})`);

  // Pattern 2a: simple await fetch(url).then(r=>r.json())
  text = text.replace(/await\s+fetch\(\s*([^\)]+)\)\.then\(\s*\w+\s*=>\s*\w+\.json\(\)\s*\)/g,
    (m, urlExpr) => `await ApiClient.get(${urlExpr.trim()})`);

  // Pattern 2b: const r = await fetch(url); const data = await r.json(); -> const data = await ApiClient.get(url);
  text = text.replace(/const\s+(\w+)\s*=\s*await\s+fetch\(([^\)]+)\);\s*\n\s*const\s+(\w+)\s*=\s*await\s*\1\.json\(\);/g,
    (m, rVar, urlExpr, dataVar) => `const ${dataVar} = await ApiClient.get(${urlExpr.trim()});`);

  // Pattern 2c: fetch(url, { credentials: 'same-origin' }).then(r=>r.json())
  text = text.replace(/fetch\(\s*([^,\)]+)\s*,\s*\{[^}]*credentials\s*:\s*['"][^'"]+['"][^}]*}\s*\)\.then\(\s*\w+\s*=>\s*\w+\.json\(\)\s*\)/g,
    (m, urlExpr) => `ApiClient.get(${urlExpr.trim()})`);

  // Pattern 3: DELETE
  text = text.replace(/await\s+fetch\(\s*([^,]+)\s*,\s*\{[^}]*method\s*:\s*['"]DELETE['"][^}]*}\s*\)/g,
    (m, urlExpr) => `await ApiClient.delete(${urlExpr.trim()})`);

  // Pattern 4: FormData uploads
  text = text.replace(/await\s+fetch\(\s*([^,]+)\s*,\s*\{[^}]*method\s*:\s*['"]POST['"][^}]*body\s*:\s*([^}\)]+)\}[^\)]*\)/gms,
    (m, urlExpr, bodyExpr) => {
      const b = bodyExpr.trim();
      if (/FormData\b/.test(b) || /\bfd\b/.test(b) || /new\s+FormData\s*\(/.test(b)) {
        return `await ApiClient.upload(${urlExpr.trim()}, ${b.replace(/[,}]$/,'')})`;
      }
      return m;
    });

  // Pattern 5: bare fetch(url, { credentials: ... }) without then/json
  text = text.replace(/await\s+fetch\(\s*([^,\)]+)\s*,\s*\{[^}]*credentials\s*:\s*['"][^'"]+['"][^}]*}\s*\)/g,
    (m, urlExpr) => `await ApiClient.get(${urlExpr.trim()})`);

  return text;
}

for (const file of files) {
  const abs = path.resolve(file);
  if (!fs.existsSync(abs)) {
    console.error(`File not found: ${file}`);
    process.exitCode = 1;
    continue;
  }
  if (!/\.(js|mjs|ts|tsx)$/.test(abs)) {
    console.error(`Skipping non-JS file: ${file}`);
    continue;
  }
  const orig = fs.readFileSync(abs, 'utf8');
  const next = transform(orig);
  if (next !== orig) {
    const relImport = computeRelativeImport(abs);
    const final = ensureImport(next, relImport);
    fs.writeFileSync(abs, final, 'utf8');
    console.log(`Refactored: ${file}`);
  } else {
    console.log(`No changes: ${file}`);
  }
}
