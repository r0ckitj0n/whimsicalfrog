#!/usr/bin/env node
/**
 * CSS Intra-file Deduplicator (PostCSS)
 * - Removes exact duplicate rule blocks within a single file.
 * - Optional: merges repeated selectors in the same at-rule context (last-wins) when --merge is passed.
 * - Preserves the last occurrence to respect cascade semantics and places merged output at the last rule.
 * - Respects at-rule context (@media, @supports, etc.).
 * - Skips non-standard/nested rules and leaves comments intact.
 *
 * Usage:
 *   node scripts/css/dedupe-intra-file.mjs --file src/styles/site-base.css [--dry] [--merge]
 *   node scripts/css/dedupe-intra-file.mjs --all [--dry] [--merge]
 */
import fs from 'fs';
import path from 'path';
import postcss from 'postcss';
import selectorParser from 'postcss-selector-parser';

const repoRoot = path.resolve(path.join(import.meta.url.replace('file://', ''), '../../..'));
const stylesDir = path.join(repoRoot, 'src', 'styles');

function parseArgs(argv) {
  const args = { files: [], dry: false, all: false, merge: false };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--dry') args.dry = true;
    else if (a === '--all') args.all = true;
    else if (a === '--merge') args.merge = true;
    else if (a === '--file') {
      const next = argv[++i];
      if (!next) throw new Error('--file requires a path');
      args.files.push(next);
    } else if (a.startsWith('--file=')) {
      args.files.push(a.slice('--file='.length));
    }
  }
  return args;
}

function collectCssFiles(dir) {
  const out = [];
  if (!fs.existsSync(dir)) return out;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) out.push(...collectCssFiles(full));
    else if (entry.isFile() && full.endsWith('.css')) out.push(full);
  }
  return out;
}

function getAtRuleContext(node) {
  const chain = [];
  let p = node.parent;
  while (p && p.type !== 'root') {
    if (p.type === 'atrule') {
      chain.push(`@${p.name} ${p.params}`.trim());
    }
    p = p.parent;
  }
  return chain.reverse().join(' > ');
}

function getRuleDeclSignature(rule) {
  // Only handle flat declarations; if nested rules exist, return null to skip
  if (!Array.isArray(rule.nodes)) return null;
  for (const n of rule.nodes) {
    if (n.type !== 'decl' && n.type !== 'comment') return null; // allow comments, skip other structures safely
  }
  const parts = [];
  for (const d of rule.nodes) {
    if (d.type !== 'decl') continue; // ignore comments
    const prop = String(d.prop || '').trim();
    const val = String(d.value || '').trim();
    const important = d.important ? ' !important' : '';
    parts.push(`${prop}:${val}${important};`);
  }
  // Keep declaration order; normalize whitespace only
  return parts.join('');
}

async function dedupeFile(absPath, dry = false, options = { merge: false }) {
  const rel = path.relative(repoRoot, absPath);
  const css = fs.readFileSync(absPath, 'utf8');
  const root = postcss.parse(css, { from: absPath });

  // Pre-pass: within a single rule, dedupe duplicate selectors in comma-separated lists
  root.walkRules(r => {
    try {
      const arr = [];
      selectorParser(selRoot => {
        selRoot.each(sel => {
          arr.push(sel.toString().trim());
        });
      }).processSync(r.selector);
      const unique = Array.from(new Set(arr));
      const newSelector = unique.join(', ');
      if (newSelector && newSelector !== r.selector) {
        r.selector = newSelector;
      }
    } catch {
      // Ignore parse errors; leave selector unchanged
    }
  });

  // Normalization pass: split comma-separated selector groups into individual rules
  root.walkRules(r => {
    let parts = [];
    try {
      selectorParser(selRoot => {
        selRoot.each(sel => parts.push(sel.toString().trim()));
      }).processSync(r.selector);
    } catch {
      parts = [];
    }
    if (parts.length > 1) {
      for (const sel of parts) {
        const clone = r.clone();
        clone.selector = sel;
        r.parent.insertBefore(r, clone);
      }
      r.remove();
    }
  });

  // Gather rules in document order
  const rules = [];
  root.walkRules(r => {
    rules.push(r);
  });

  // Pass 1: build keys in bottom-up order to keep last occurrence
  const seen = new Set();
  const toRemove = new Set();

  for (let i = rules.length - 1; i >= 0; i--) {
    const r = rules[i];
    const ctx = getAtRuleContext(r);
    const declSig = getRuleDeclSignature(r);
    if (declSig == null) continue; // skip complex rules
    const key = `${ctx}||${r.selector}||${declSig}`;
    if (seen.has(key)) {
      toRemove.add(r);
    } else {
      seen.add(key);
    }
  }

  // Stats and (optional) mutate
  const removeCount = toRemove.size;
  let mergedGroups = 0;
  let mergedRulesRemoved = 0;
  if (!dry) {
    for (const r of toRemove) r.remove();

    // Optional merge pass: consolidate repeated selectors under same at-rule context
    if (options.merge) {
      // Build groups: (context, selector) -> list of rules in document order
      const groups = new Map();
      root.walkRules(r => {
        // Only consider flat rules (decls and comments only)
        if (!Array.isArray(r.nodes) || r.nodes.some(n => n.type !== 'decl' && n.type !== 'comment')) return;
        const ctx = getAtRuleContext(r);
        const key = `${ctx}||${r.selector}`;
        const arr = groups.get(key) || [];
        arr.push(r);
        groups.set(key, arr);
      });

      for (const [key, arr] of groups.entries()) {
        if (arr.length <= 1) continue;
        // Compute combined declarations with last-wins ordering
        const declIndex = new Map(); // prop -> { idx, decl }
        let seq = 0;
        for (const rule of arr) {
          for (const d of rule.nodes) {
            const prop = String(d.prop || '').trim();
            const val = String(d.value || '').trim();
            const important = !!d.important;
            // Use only prop as key; last occurrence wins
            declIndex.set(prop, { idx: seq++, prop, val, important });
          }
        }
        const kept = Array.from(declIndex.values()).sort((a, b) => a.idx - b.idx);
        const lastRule = arr[arr.length - 1];
        // Replace last rule's declarations but preserve existing comments
        lastRule.walkDecls(d => d.remove());
        for (const k of kept) {
          lastRule.append(postcss.decl({ prop: k.prop, value: k.val, important: k.important }));
        }
        // Remove all but the last rule
        for (let i = 0; i < arr.length - 1; i++) {
          arr[i].remove();
          mergedRulesRemoved++;
        }
        mergedGroups++;
      }
    }

    // Pass 2.5: Prune redundant at-rule context copies where an identical global rule exists
    // Only consider flat rules (decls/comments). If a global rule (no at-rule context) for the same selector
    // has an identical declaration signature, remove the contextual copy as redundant.
    {
      const flatRules = [];
      let order = 0;
      root.walkRules(r => {
        if (!Array.isArray(r.nodes) || r.nodes.some(n => n.type !== 'decl' && n.type !== 'comment')) return;
        const ctx = getAtRuleContext(r);
        const declSig = getRuleDeclSignature(r);
        if (declSig == null) return;
        flatRules.push({ node: r, selector: r.selector, ctx, declSig, order: order++ });
      });
      const lastGlobalBySelector = new Map(); // selector -> { declSig, order, node }
      for (const fr of flatRules) {
        if (fr.ctx === '') {
          // keep the last (highest order) global rule for the selector
          const prev = lastGlobalBySelector.get(fr.selector);
          if (!prev || prev.order <= fr.order) lastGlobalBySelector.set(fr.selector, { declSig: fr.declSig, order: fr.order, node: fr.node });
        }
      }
      let prunedContextRules = 0;
      for (const fr of flatRules) {
        if (fr.ctx === '') continue; // only contextual rules
        const g = lastGlobalBySelector.get(fr.selector);
        if (!g) continue;
        if (g.declSig === fr.declSig) {
          fr.node.remove();
          prunedContextRules++;
        }
      }
      if (prunedContextRules > 0) {
        console.log(`   - pruned ${prunedContextRules} redundant at-rule context rule(s)`);
      }
    }

    // After removing duplicate rules (and optional merges/prunes), prune any empty rules and empty at-rule containers
    // 1) Remove empty rules (no declarations)
    root.walkRules(r => {
      const hasDecl = Array.isArray(r.nodes) && r.nodes.some(n => n.type === 'decl');
      if (!hasDecl) {
        r.remove();
      }
    });

    // 2) Iteratively remove empty at-rules (@media, @supports, etc.)
    let removedSomething = true;
    while (removedSomething) {
      removedSomething = false;
      root.walkAtRules(ar => {
        // Preserve blockless at-rules that are valid without bodies, e.g. @import, @charset, @namespace, and blockless @layer
        const name = String(ar.name || '').toLowerCase();
        const isBlockless = !Array.isArray(ar.nodes) || ar.nodes.length === 0;
        const keepBlockless = (name === 'import' || name === 'charset' || name === 'namespace' || name === 'layer') && isBlockless;
        if (keepBlockless) {
          return; // do not remove valid blockless at-rules
        }
        const hasContent = Array.isArray(ar.nodes) && ar.nodes.some(n => n.type === 'rule' || n.type === 'decl' || n.type === 'atrule');
        if (!hasContent) {
          ar.remove();
          removedSomething = true;
        }
      });
    }
    const out = root.toString();
    fs.writeFileSync(absPath, out, 'utf8');
  }
  // In dry-run, estimate merge opportunities without mutating
  if (dry && options.merge) {
    const groups = new Map();
    root.walkRules(r => {
      if (!Array.isArray(r.nodes) || r.nodes.some(n => n.type !== 'decl')) return;
      const ctx = getAtRuleContext(r);
      const key = `${ctx}||${r.selector}`;
      const arr = groups.get(key) || [];
      arr.push(r);
      groups.set(key, arr);
    });
    for (const arr of groups.values()) {
      if (arr.length > 1) {
        mergedGroups++;
        mergedRulesRemoved += (arr.length - 1);
      }
    }
  }
  return { file: rel, removed: removeCount, mergedGroups, mergedRulesRemoved };
}

async function main() {
  const args = parseArgs(process.argv);
  let files = args.files.map(p => (path.isAbsolute(p) ? p : path.join(repoRoot, p)));
  if (args.all || files.length === 0) {
    files = collectCssFiles(stylesDir);
  }

  if (files.length === 0) {
    console.log('No CSS files found to process.');
    process.exit(0);
  }

  console.log(`CSS Deduper: processing ${files.length} file(s). Dry-run=${args.dry} Merge=${args.merge}`);
  let totalRemoved = 0;
  let totalMergedGroups = 0;
  let totalMergedRulesRemoved = 0;
  for (const f of files) {
    if (!fs.existsSync(f)) {
      console.warn(`Skip missing: ${f}`);
      continue;
    }
    const { file, removed, mergedGroups, mergedRulesRemoved } = await dedupeFile(f, args.dry, { merge: args.merge });
    if (removed > 0 || mergedGroups > 0) {
      const parts = [];
      if (removed > 0) parts.push(`remove ${removed} duplicate block(s)`);
      if (mergedGroups > 0) parts.push(`merge ${mergedGroups} group(s) (-${mergedRulesRemoved} rules)`);
      console.log(` - ${file}: ${parts.join(', ')}`);
    }
    totalRemoved += removed;
    totalMergedGroups += mergedGroups;
    totalMergedRulesRemoved += mergedRulesRemoved;
  }
  const tail = args.dry ? '(dry-run) ' : '';
  console.log(`Done. ${tail}Total duplicates${args.dry ? ' detected' : ' removed'}: ${totalRemoved}. ${args.merge ? `Total merged groups: ${totalMergedGroups}, rules removed by merge: ${totalMergedRulesRemoved}` : ''}`);
}

main().catch(e => {
  console.error(e);
  process.exit(1);
});
