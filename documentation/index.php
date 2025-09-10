<?php
// Simple Documentation Portal with TOC and Search
// Scans the documentation/ directory for .md files and renders them client-side.

// Collect markdown files
$root = __DIR__;
$files = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (substr($path, -3) !== '.md') continue;
    // Skip node_modules or hidden
    if (strpos($path, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) !== false) continue;
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $files[] = $rel;
}
// Sort for stable TOC
sort($files, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>WhimsicalFrog Documentation</title>
  <style>
    :root { --bg:#0f172a; --panel:#111827; --text:#e5e7eb; --muted:#94a3b8; --accent:#87ac3a; }
    html,body{height:100%;}
    body{margin:0; background:var(--bg); color:var(--text); font:14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';}
    .layout{display:grid; grid-template-columns: 300px 1fr; gap:0; height:100vh;}
    .sidebar{background:var(--panel); border-right:1px solid #1f2937; display:flex; flex-direction:column;}
    .search{padding:12px; border-bottom:1px solid #1f2937;}
    .search input{width:100%; padding:10px 12px; border-radius:8px; border:1px solid #374151; background:#0b1220; color:var(--text);}
    .toc{overflow:auto; padding:8px 0;}
    .toc a{display:block; color:var(--text); text-decoration:none; padding:8px 12px; border-radius:6px; font-size:13px;}
    .toc a:hover{background:#0b1220;}
    .toc .muted{color:var(--muted); font-size:12px;}
    .content{padding:16px 24px; overflow:auto;}
    .doc{max-width: 1200px;}
    .doc h1,.doc h2,.doc h3{color:#ffffff;}
    .doc pre{background:#0b1220; padding:12px; border-radius:8px; overflow:auto;}
    .doc code{background:#0b1220; padding:2px 4px; border-radius:4px;}
    .topbar{display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 16px; border-bottom:1px solid #1f2937;}
    .topbar .title{font-weight:700;}
    .kbd{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; background:#0b1220; border:1px solid #374151; padding:2px 6px; border-radius:6px; font-size:12px;}
    .empty{color:var(--muted); padding:12px;}
    @media (max-width: 900px){ .layout{grid-template-columns: 1fr;} .sidebar{position:sticky; top:0; z-index:10;} }
    .hidden{ display:none !important; }
    .doc-error{ color:#fca5a5; }
  </style>
  <script src="https://unpkg.com/marked@12/marked.min.js" defer></script>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="topbar">
        <div class="title">Documentation</div>
        <div class="kbd">/? search</div>
      </div>
      <div class="search"><input id="search" type="search" placeholder="Search topics..." aria-label="Search documentation"></div>
      <nav class="toc" id="toc">
        <?php if (empty($files)): ?>
          <div class="empty">No documentation files found.</div>
        <?php else: ?>
          <?php foreach ($files as $rel): $name = basename($rel, '.md'); $dir = dirname($rel); ?>
            <a href="#<?= htmlspecialchars($rel) ?>" data-doc="<?= htmlspecialchars($rel) ?>">
              <?= htmlspecialchars($name) ?>
              <?php if ($dir !== '.'): ?><span class="muted">/<?= htmlspecialchars($dir) ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </nav>
    </aside>
    <main class="content">
      <div id="doc" class="doc">Select a topic from the left, or start typing to filter.</div>
    </main>
  </div>
  <script>
    window.addEventListener('DOMContentLoaded', () => {
      const toc = document.getElementById('toc');
      const search = document.getElementById('search');
      const doc = document.getElementById('doc');

      // Keyboard focus for search
      document.addEventListener('keydown', (e) => {
        if (e.key === '/' && !e.target.matches('input, textarea')) { e.preventDefault(); search.focus(); }
      });

      // Filter TOC
      search.addEventListener('input', () => {
        const q = search.value.toLowerCase();
        toc.querySelectorAll('a[data-doc]').forEach(a => {
          const s = a.textContent.toLowerCase();
          if (s.includes(q)) { a.classList.remove('hidden'); }
          else { a.classList.add('hidden'); }
        });
      });

      async function loadDoc(rel){
        if (!rel) return;
        try {
          doc.innerHTML = 'Loading…';
          const res = await fetch(rel);
          if (!res.ok) throw new Error('HTTP '+res.status);
          const md = await res.text();
          const html = (window.marked ? window.marked.parse(md) : md);
          doc.innerHTML = html;
          // Scroll to top
          document.querySelector('.content').scrollTo({top:0,behavior:'smooth'});
        } catch (err) {
          doc.innerHTML = `<div class="doc-error">Failed to load document: ${rel}. ${err && err.message ? err.message : ''}</div>`;
        }
      }

      // Click handler
      toc.addEventListener('click', (e) => {
        const a = e.target.closest('a[data-doc]');
        if (!a) return;
        e.preventDefault();
        const rel = a.getAttribute('data-doc');
        history.replaceState(null, '', `#${rel}`);
        loadDoc(rel);
      });

      // Deep-link support via hash
      const initial = location.hash ? location.hash.slice(1) : '';
      if (initial) loadDoc(initial);
    });
  </script>
</body>
</html>
