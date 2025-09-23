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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Documentation</title>
    <?php vite('js/help-documentation.js'); ?>
</head>
<body style="margin:0;padding:20px;font-family:system-ui;background:#fff">
    <div class="help-documentation-container" data-page="help-documentation">
        <div style="background:linear-gradient(135deg,#3b82f6,#8b5cf6);color:white;padding:24px;border-radius:8px;margin-bottom:24px">
            <h1 style="margin:0 0 8px 0;font-size:24px">📚 Help Documentation</h1>
            <p style="margin:0;opacity:0.9">Guide to using your e-commerce platform</p>
        </div>

        <div style="margin-bottom:24px">
            <input type="text" id="helpSearch" placeholder="Search documentation..." 
                   style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font-size:16px;box-sizing:border-box">
        </div>

        <div style="display:grid;grid-template-columns:200px 1fr;gap:24px;min-height:600px">
            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px">
                <h3 style="margin:0 0 16px 0">📋 Contents</h3>
                <nav id="docsList" style="display:flex;flex-direction:column;gap:4px"></nav>
            </div>
            <div id="helpContent" style="background:white;border:1px solid #e5e7eb;border-radius:8px;padding:24px;min-height:500px;overflow-y:auto">
                <div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:16px;margin-bottom:24px;border-radius:4px">
                    <h3 style="margin:0 0 8px 0;color:#1e40af;font-weight:600">🚀 Getting Started</h3>
                    <p style="margin:0;color:#1e40af">Welcome to your e-commerce platform! Click on the sections in the sidebar to navigate through the documentation.</p>
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
      <li style="margin:6px 0">
        <a href="javascript:void(0)" onclick="loadDoc('${d.filename||''}')" style="color:#2563eb;text-decoration:none">${d.title||d.filename}</a>
        <div style="font-size:12px;color:#6b7280">${d.category||''}</div>
      </li>
    `).join('');
    document.getElementById('helpContent').innerHTML =
      `<h2 style="margin:0 0 12px 0">📚 All Documentation</h2><ul style="padding-left:16px">${items}</ul>`;
  } catch(e) {
    document.getElementById('helpContent').innerHTML =
      `<div style="color:#b91c1c">Failed to load docs: ${e.message}</div>`;
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
      `<h2 style="margin:0 0 12px 0">${title}</h2><div>${renderMarkdown(md)}</div>`;
  } catch(e) {
    document.getElementById('helpContent').innerHTML =
      `<div style="color:#b91c1c">Failed to load document: ${e.message}</div>`;
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
  h = h.replace(/```([\s\S]*?)```/g, (m, code) => `<pre style="background:#f3f4f6;padding:12px;border-radius:6px;overflow:auto"><code>${code.replace(/</g,'&lt;')}</code></pre>`);
  h = h.replace(/^\s*\*\s+(.*)$/gm,'<li>$1</li>').replace(/(<li>.*<\/li>\n?)+/g, m => `<ul style="padding-left:20px;line-height:1.6">${m}</ul>`);
  h = h.replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>').replace(/\*([^*]+)\*/g,'<em>$1</em>');
  h = h.replace(/\[([^\]]+)\]\(([^)]+)\)/g,'<a href="$2" target="_blank" rel="noopener">$1</a>');
  return h;
}
</script>
    <script>
        function loadHelpSection(sectionId) {
            // Update active nav
            document.querySelectorAll('.toc-link').forEach(link => {
                link.style.color = '#6b7280';
                link.style.background = 'transparent';
                link.style.fontWeight = 'normal';
            });
            
            event.target.style.color = '#2563eb';
            event.target.style.background = '#dbeafe';
            event.target.style.fontWeight = '500';
            
            // Load content
            const content = {
                'getting-started': `
                    <h2 style="font-size:24px;font-weight:bold;margin:0 0 20px 0;color:#1f2937">🚀 Getting Started</h2>
                    <div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:16px;margin:20px 0;border-radius:8px">
                        <p style="margin:0;color:#1e40af;line-height:1.6">Welcome! This platform manages your complete online store from inventory to customer orders.</p>
                    </div>
                    <h3 style="font-size:18px;font-weight:600;margin:24px 0 12px 0;color:#374151">First Steps:</h3>
                    <ol style="padding-left:20px;line-height:1.8;color:#6b7280">
                        <li style="margin-bottom:8px">Set up business info in <strong style="color:#374151">Settings → Business Information</strong></li>
                        <li style="margin-bottom:8px">Configure payments in <strong style="color:#374151">Settings → Configure Square</strong></li>
                        <li style="margin-bottom:8px">Add products in <strong style="color:#374151">Admin → Inventory</strong></li>
                        <li style="margin-bottom:8px">Set up categories in <strong style="color:#374151">Admin → Categories</strong></li>
                        <li style="margin-bottom:8px">Configure room layouts in <strong style="color:#374151">Settings → Room Settings</strong></li>
                    </ol>
                    <div style="background:#fffbeb;border-left:4px solid #f59e0b;padding:16px;margin:20px 0;border-radius:8px">
                        <h4 style="margin:0 0 8px 0;font-weight:600;color:#92400e">💡 Pro Tip</h4>
                        <p style="margin:0;color:#92400e;line-height:1.6">Use the tooltips throughout the admin interface for contextual help. Toggle them on/off in Settings → Help & Hints.</p>
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
