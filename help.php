<?php
// Standalone Help Documentation (no admin layout)
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/auth.php';

// Temporarily allow access for testing
// TODO: Re-enable auth check after content is verified
/*
if (!isLoggedIn()) {
    http_response_code(403);
    echo '<div class="p-6 text-center text-red-600">Please log in to access help documentation</div>';
    exit;
}
*/

require_once __DIR__ . '/includes/vite_helper.php';

// Lightweight read-only docs proxy for Help UI (no auth required)
// Usage: /help.php?docs=list  or  /help.php?docs=get&file=relative/path.md
if (isset($_GET['docs'])) {
    header('Content-Type: application/json; charset=utf-8');

    $root = realpath(__DIR__ . '/documentation');
    $action = $_GET['docs'];

    // safe join inside /documentation
    $safe = function ($rel) use ($root) {
        $rel = ltrim(str_replace(['\\', '..'], ['/', ''], $rel), '/');
        $path = realpath($root . '/' . $rel);
        return ($path && strpos($path, $root) === 0) ? $path : false;
    };

    if ($action === 'list') {
        $docs = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'md') {
                $path = $f->getPathname();
                $rel  = ltrim(str_replace($root, '', $path), '/');
                $content = @file_get_contents($path) ?: '';
                $title = (preg_match('/^#\\s+(.+)$/m', $content, $m)) ? trim($m[1]) : basename($path);
                $parts = explode('/', $rel);
                $category = count($parts) > 1 ? ucfirst($parts[0]) : 'General';
                $docs[] = ['filename' => $rel, 'title' => $title, 'category' => $category];
            }
        }
        echo json_encode(['success' => true, 'documents' => $docs], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'get') {
        $rel = $_GET['file'] ?? '';
        $path = $safe($rel);
        if (!$path || !is_file($path)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        $content = @file_get_contents($path) ?: '';
        $title = (preg_match('/^#\\s+(.+)$/m', $content, $m)) ? trim($m[1]) : basename($path);
        echo json_encode(['success' => true, 'document' => ['title' => $title, 'content' => $content]]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid docs action']);
    exit;
}
?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Documentation</title>
    <?php vite('js/help-documentation.js'); ?>
</head>
<body class="help-body">
    <div class="help-documentation-container" data-page="help-documentation">
        <div class="help-hero">
            <h1 class="help-hero__title">📚 Help Documentation</h1>
            <p class="help-hero__subtitle">Guide to using your e-commerce platform</p>
            

        <div class="help-search">
            <input type="text" id="helpSearch" placeholder="Search documentation..." class="help-search__input">
        </div>

        <div class="help-grid">
            <div class="help-sidebar">
                <h3>📋 Contents</h3>
                <nav id="docsList" class="help-sidebar__nav"></nav>
            </div>
            <div id="helpContent" class="help-content">
                <div class="help-callout help-callout--info">
                    <h3>🚀 Getting Started</h3>
                    <p>Welcome to your e-commerce platform! Click on the sections in the sidebar to navigate through the documentation.</p>
                </div>
            </div>
        </div>
    </div>
    <script>
async function loadDocsList(){
  try {
    const r = await fetch('/help.php?docs=list');
    const j = await r.json();
    if (!j.success) throw new Error(j.error||'Failed');
    const items = (j.documents||[]).map(d => `
      <li class="help-docs__item">
        <a href="#" data-file="${d.filename||''}" class="help-docs__link js-doc-link">${d.title||d.filename}</a>
        <div class="help-docs__category">${d.category||''}</div>
      </li>
    `).join('');
    document.getElementById('helpContent').innerHTML =
      `<h2 class="help-content__title">📚 All Documentation</h2><ul class="help-docs__list">${items}</ul>`;
  } catch(e) {
    document.getElementById('helpContent').innerHTML =
      `<div class="help-error">Failed to load docs: ${e.message}</div>`;
  }
}

async function loadDoc(file){
  try {
    const r = await fetch('/help.php?docs=get&file='+encodeURIComponent(file));
    const j = await r.json();
    if (!j.success) throw new Error(j.error||'Failed');
    const md = (j.document && j.document.content) || '';
    const title = (j.document && j.document.title) || file;
    document.getElementById('helpContent').innerHTML =
      `<h2 class="help-content__title">${title}</h2><div>${renderMarkdown(md)}</div>`;
  } catch(e) {
    document.getElementById('helpContent').innerHTML =
      `<div class="help-error">Failed to load document: ${e.message}</div>`;
  }
}

// ultra-light markdown renderer (headings, lists, code, bold/italic, links)
function renderMarkdown(md){
  let h = md.replace(/[&<>]/g, s => ({"&":"&amp;","<":"&lt;",">":"&gt;"}[s]));
  h = h.replace(/^######\s*(.*)$/gm,'<h6>$1</h6>')
       .replace(/^#####\s*(.*)$/gm,'<h5>$1</h5>')
       .replace(/^####\s*(.*)$/gm,'<h4>$1</h4>')
       .replace(/^###\s*(.*)$/gm,'<h3>$1</h3>')
       .replace(/^##\s*(.*)$/gm,'<h2>$1</h2>')
       .replace(/^#\s*(.*)$/gm,'<h1>$1</h1>');
  h = h.replace(/```([\s\S]*?)```/g, (m, code) => `<pre class=\"help-code\"><code>${code.replace(/</g,'&lt;')}</code></pre>`);
  h = h.replace(/^\s*\*\s+(.*)$/gm,'<li>$1</li>').replace(/(<li>.*<\/li>\n?)+/g, m => `<ul class=\"help-list\">${m}</ul>`);
  h = h.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/\*([^*]+)\*/g,'<em>$1</em>');
  h = h.replace(/\[([^\]]+)\]\(([^)]+)\)/g,'<a href="$2" target="_blank" rel="noopener">$1</a>');
  return h;
}
</script>
    <script>
        // Delegated click handler for docs links (replaces inline onclick)
        document.addEventListener('click', function(ev){
            const a = ev.target && ev.target.closest ? ev.target.closest('.js-doc-link') : null;
            if (!a) return;
            ev.preventDefault();
            const f = a.getAttribute('data-file') || '';
            if (f) loadDoc(f);
        });
        function loadHelpSection(sectionId) {
            try {
                document.querySelectorAll('.toc-link').forEach(link => link.classList.remove('active'));
                var anchor = document.querySelector('.toc-link[href="#'+sectionId+'"]');
                if (anchor) anchor.classList.add('active');
            } catch(_) {}
            // Load content
            const content = {
                'getting-started': `
                    <h2 class=\"help-h2\">🚀 Getting Started</h2>
                    <div class=\"help-callout help-callout--info\">
                        <p>Welcome! This platform manages your complete online store from inventory to customer orders.</p>
                    </div>
                    <h3 class=\"help-section-title\">First Steps:</h3>
                    <ol class=\"help-ol\">
                        <li class=\"help-ol__item\">Set up business info in <strong>Settings → Business Information</strong></li>
                        <li class=\"help-ol__item\">Configure payments in <strong>Settings → Configure Square</strong></li>
                        <li class=\"help-ol__item\">Add products in <strong>Admin → Inventory</strong></li>
                        <li class=\"help-ol__item\">Set up categories in <strong>Admin → Categories</strong></li>
                        <li class=\"help-ol__item\">Configure room layouts in <strong>Settings → Room Settings</strong></li>
                    </ol>
                    <div class=\"help-callout help-callout--tip\">
                        <h4>💡 Pro Tip</h4>
                        <p>Use the tooltips throughout the admin interface for contextual help. Toggle them on/off in Settings → Help & Hints.</p>
                    </div>
                `,
                'inventory': `<h2>📦 Inventory</h2><p><strong>Route:</strong> /admin/?section=inventory</p><h3>Features:</h3><ul><li>Product management with variants</li><li>Stock tracking and alerts</li><li>AI pricing suggestions</li><li>Categories management</li><li>Image optimization</li></ul>`,
                'orders': `<h2>📋 Orders</h2><p>Route: /admin/?section=orders</p>`,
                'rooms': '<h2>🏠 Rooms</h2><p>Configure interactive room layouts.</p>',
                'payments': '<h2>💳 Payments</h2><p>Set up Square payment processing.</p>'
            };
            
            document.getElementById('helpContent').innerHTML = content[sectionId] || content['getting-started'];
        }
        
        // Load content on page load
        window.onload = function() {
            loadHelpSection('getting-started');
        };
    </script>
</body>
</html>
