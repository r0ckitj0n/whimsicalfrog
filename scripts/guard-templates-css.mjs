#!/usr/bin/env node
/**
 * Guard templates for legacy CSS links and inline style attributes.
 * - Fails if any live PHP/HTML templates contain:
 *   - <link rel="stylesheet" ...>
 *   - href="*.css"
 *   - style="..." attributes
 * - Ignores known safe directories: backups/** and API email templates.
 */
import { readFileSync } from 'node:fs';
import { globSync } from 'glob';

const ROOT = new URL('..', import.meta.url).pathname;

const files = globSync('**/*.{php,html,htm}', {
  cwd: ROOT,
  dot: false,
  ignore: [
    'node_modules/**',
    'dist/**',
    'public/**',
    'backups/**',
    'backups*/**',
    'logs/**',
    // Allow email templates and configs to include inline styles
    'api/*email*.*',
    'api/**/email*.*',
    'api/**/emails/**',
    'api/**/email/**',
  ],
});

const violations = [];

/** Strip HTML comments to reduce false positives */
function stripHtmlComments(src) {
  return src.replace(/<!--([\s\S]*?)-->/g, '');
}

const linkRelStylesheetRe = /<link\b[^>]*\brel\s*=\s*(["'])stylesheet\1[^>]*>/i;
const hrefCssRe = /\bhref\s*=\s*(["'])[^"]+\.css\1/i;
const inlineStyleAttrRe = /\sstyle\s*=\s*(["'])[\s\S]*?\1/gi;

for (const relPath of files) {
  const filePath = `${ROOT}${relPath}`;
  let src = '';
  try {
    src = readFileSync(filePath, 'utf8');
  } catch (e) {
    // Skip unreadable files
    continue;
  }

  // IMPORTANT: Check ignore token on the raw source BEFORE stripping comments
  // so that HTML comment tokens like <!-- WF_GUARD_TEMPLATES_CSS_IGNORE --> are honored.
  if (/WF_GUARD_TEMPLATES_CSS_IGNORE/.test(src)) continue;

  const content = stripHtmlComments(src);

  const hits = [];
  if (linkRelStylesheetRe.test(content)) {
    hits.push('link rel="stylesheet"');
  }
  if (hrefCssRe.test(content)) {
    hits.push('href="*.css"');
  }
  const styleAttrs = content.match(inlineStyleAttrRe) || [];
  if (styleAttrs.length) {
    hits.push(`inline style attributes (${styleAttrs.length})`);
  }
  if (hits.length) {
    violations.push({ file: relPath, issues: hits });
  }
}

if (violations.length) {
  console.error('\n[guard-templates-css] Violations found in templates:');
  for (const v of violations) {
    console.error(` - ${v.file}: ${v.issues.join(', ')}`);
  }
  console.error('\nFix guidance:');
  console.error(' * Remove legacy <link rel="stylesheet"> tags and href="*.css" links; Vite injects CSS from js/app.js');
  console.error(' * Move inline style attributes into CSS classes managed under src/styles/ and referenced from modules');
  console.error(' * If this is an intentional email template, place it under api/**/email* or add WF_GUARD_TEMPLATES_CSS_IGNORE');
  process.exitCode = 1;
} else {
  console.log('[guard-templates-css] OK: no legacy CSS links or inline style attributes found in live templates.');
}
