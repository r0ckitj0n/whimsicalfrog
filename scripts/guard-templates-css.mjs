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

// Temporary allowlist for legacy inline <style> blocks that are being migrated
// into Vite-managed CSS. This prevents *new* inline CSS from sneaking in while
// we gradually clean up existing templates.
//
// IMPORTANT: Do not add new files here. Instead, move CSS into src/styles/**
// and reference it from Vite entries. Once migration is complete, this list
// should be emptied and removed.
const INLINE_STYLE_ALLOWLIST = new Set([]);

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

const linkRelStylesheetRe = /<link\b[^>]*\brel\s*=\s*(["'])stylesheet\1[^>]*>/ig;
const hrefCssRe = /\bhref\s*=\s*(["'])([^"']+\.css[^"']*)\1/gi;
const inlineStyleAttrRe = /\sstyle\s*=\s*(["'])[\s\S]*?\1/gi;
const styleTagRe = /<style\b[^>]*>/i;

function isSafeStylesheetHref(href) {
  if (!href) return false;
  const trimmed = href.trim();
  // Google Fonts stylesheets
  if (/^https:\/\/fonts\.googleapis\.com\//i.test(trimmed)) return true;
  // Dynamic admin icon map CSS
  if (/^\/api\/admin_icon_map\.php\b/i.test(trimmed)) return true;
  // Vite main CSS in dev/safe modes (any origin)
  if (trimmed.includes('/src/styles/main.css')) return true;
  return false;
}

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

  // Flag only unsafe stylesheet links; allow known-safe ones (fonts, Vite main.css, admin icon map)
  const unsafeLinks = [];
  let m;
  while ((m = linkRelStylesheetRe.exec(content)) !== null) {
    const tag = m[0];
    const hrefMatch = tag.match(/\bhref\s*=\s*(["'])([^"']+)\1/i);
    const href = hrefMatch ? hrefMatch[2] : '';
    if (!isSafeStylesheetHref(href)) {
      unsafeLinks.push(tag);
    }
  }
  if (unsafeLinks.length) {
    hits.push('link rel="stylesheet"');
  }

  // Flag href="*.css" only when the target is not in the known-safe set
  const unsafeHrefCss = [];
  let hrefMatch;
  while ((hrefMatch = hrefCssRe.exec(content)) !== null) {
    const href = hrefMatch[2];
    if (!isSafeStylesheetHref(href)) {
      unsafeHrefCss.push(href);
    }
  }
  if (unsafeHrefCss.length) {
    hits.push('href="*.css"');
  }
  const styleAttrs = content.match(inlineStyleAttrRe) || [];
  if (styleAttrs.length) {
    hits.push(`inline style attributes (${styleAttrs.length})`);
  }
  // Guard against inline <style> blocks. Existing legacy usages are
  // temporarily allowlisted above to avoid breaking builds while they
  // are migrated into Vite-managed CSS.
  if (styleTagRe.test(content) && !INLINE_STYLE_ALLOWLIST.has(relPath)) {
    hits.push('<style> blocks');
  }

  // Special-case header.php: it is responsible for global CSS links (Vite main.css,
  // fonts, admin icon map). We still enforce inline <style> and style="..." but
  // allow its stylesheet links to pass.
  if (relPath === 'partials/header.php') {
    const filtered = hits.filter(h => h !== 'link rel="stylesheet"' && h !== 'href="*.css"');
    hits.length = 0;
    hits.push(...filtered);
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
