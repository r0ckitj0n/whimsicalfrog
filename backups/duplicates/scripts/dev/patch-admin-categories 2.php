<?php

// scripts/dev/patch-admin-categories.php

echo "Attempting to patch sections/admin_categories.php with tabbed UI...\n";

$targetFile = __DIR__ . '/../../sections/admin_categories.php';
$backupFile = $targetFile . '.bak.' . time();
$patchMarker = '<!-- PATCH_ADMIN_CATEGORIES_TABS_APPLIED -->';

if (!file_exists($targetFile)) {
    die("ERROR: Target file not found at {$targetFile}\n");
}

$content = file_get_contents($targetFile);

if (strpos($content, $patchMarker) !== false) {
    die("INFO: Patch has already been applied. Aborting.\n");
}

// 1. Create a backup
if (!copy($targetFile, $backupFile)) {
    die("ERROR: Failed to create backup file at {$backupFile}\n");
}
echo "SUCCESS: Backup created at {$backupFile}\n";

// 2. Define the new code blocks

$tabsNavigationAndPanelStart = <<<'HTML'
    <!-- Tabs Navigation -->
    <div class="admin-card" style="margin: 8px 0;">
      <div class="admin-form-inline" role="tablist" aria-label="Category Management Tabs" style="gap: 8px;">
        <button type="button" id="tabBtnCategories" class="btn btn-primary" aria-selected="true" aria-controls="tabPanelCategories">Categories</button>
        <button type="button" id="tabBtnAssignments" class="btn" aria-selected="false" aria-controls="tabPanelAssignments">Assignments</button>
        <button type="button" id="tabBtnOverview" class="btn" aria-selected="false" aria-controls="tabPanelOverview">Overview</button>
      </div>
    </div>

    <!-- Tab Panels -->
    <div id="tabPanelCategories" role="tabpanel" aria-labelledby="tabBtnCategories">
HTML;

$panelsEndAndScript = <<<'HTML'
    </div> <!-- end of tabPanelCategories -->

    <!-- Assignments Panel (initially hidden) -->
    <div id="tabPanelAssignments" role="tabpanel" aria-labelledby="tabBtnAssignments" style="display:none">
      <div class="admin-card">
        <h3 class="admin-card-title">Room-Category Assignments</h3>
        <div id="rcAssignmentsContainer" class="admin-table-wrapper">
          <div class="text-gray-600 text-sm">Loading assignments…</div>
        </div>
      </div>
    </div>

    <!-- Overview Panel (per-room summary) -->
    <div id="tabPanelOverview" role="tabpanel" aria-labelledby="tabBtnOverview" style="display:none">
      <div class="admin-card">
        <h3 class="admin-card-title">Per-Room Overview</h3>
        <div id="rcOverviewContainer" class="space-y-2">
          <div class="text-gray-600 text-sm">Loading overview…</div>
        </div>
      </div>
    </div>

    <script>
      (function() {
        const tabs = [
          {btn: 'tabBtnCategories', panel: 'tabPanelCategories'},
          {btn: 'tabBtnAssignments', panel: 'tabPanelAssignments'},
          {btn: 'tabBtnOverview', panel: 'tabPanelOverview'}
        ];
        function showPanel(key) {
          tabs.forEach(t => {
            const btn = document.getElementById(t.btn);
            const panel = document.getElementById(t.panel);
            const active = (t.panel === key);
            if (btn) btn.setAttribute('aria-selected', active ? 'true' : 'false');
            if (panel) panel.style.display = active ? '' : 'none';
            if (btn) {
                btn.classList.toggle('btn-primary', active);
                if (!active) btn.classList.remove('btn-primary');
            }
          });
          if (key === 'tabPanelOverview') loadOverview();
          if (key === 'tabPanelAssignments') loadAssignments();
        }
        tabs.forEach(t => {
          const btn = document.getElementById(t.btn);
          if (btn) btn.addEventListener('click', () => showPanel(t.panel));
        });
        showPanel('tabPanelCategories'); // default

        async function fetchJSON(url) {
          const res = await fetch(url, { credentials: 'same-origin' });
          if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
          return res.json();
        }

        async function loadOverview() {
          const el = document.getElementById('rcOverviewContainer');
          if (!el) return;
          el.innerHTML = '<div class="text-gray-600 text-sm">Loading overview…</div>';
          try {
            const data = await fetchJSON('/api/room_category_assignments.php?action=get_summary');
            if (!data?.success) throw new Error(data?.message || 'Failed to load summary');
            if (!data.summary?.length) {
              el.innerHTML = '<div class="text-gray-600 text-sm">No room-category assignments found.</div>';
              return;
            }
            el.innerHTML = data.summary.map(row => {
              const primary = row.primary_category ? ` <span class="code-badge">Primary: ${row.primary_category}</span>` : '';
              return `<div class="admin-info-card"><b>Room ${row.room_number}${row.room_name ? ` (${row.room_name})` : ''}</b>: ${row.categories || '—'}${primary}</div>`;
            }).join('');
          } catch (e) {
            el.innerHTML = `<div class="text-danger">Error loading overview: ${e.message}</div>`;
          }
        }

        async function loadAssignments() {
          const el = document.getElementById('rcAssignmentsContainer');
          if (!el) return;
          el.innerHTML = '<div class="text-gray-600 text-sm">Loading assignments…</div>';
          try {
            const data = await fetchJSON('/api/room_category_assignments.php?action=get_all');
            if (!data?.success) throw new Error(data?.message || 'Failed to load assignments');
            if (!data.assignments?.length) {
              el.innerHTML = '<div class="text-gray-600 text-sm">No assignments found.</div>';
              return;
            }
            // Simple list for now
            el.innerHTML = '<ul>' + data.assignments.map(a => `<li>Room ${a.room_number}: ${a.category_name} (Order: ${a.display_order})</li>`).join('') + '</ul>';
          } catch (e) {
            el.innerHTML = `<div class="text-danger">Error loading assignments: ${e.message}</div>`;
          }
        }
      })();
    </script>
HTML;

// 3. Perform the replacements

// Find the header section to insert tabs after it
$headerMarker = '    <div class="admin-header-section">';
$content = str_replace($headerMarker, $headerMarker . "\n" . $tabsNavigationAndPanelStart, $content);

// Find the final closing div to insert the rest of the content before it
$closingDivMarker = "\n</div>\n\n"; // Looking for the final </div> of the main container
$lastPos = strrpos($content, $closingDivMarker);
if ($lastPos !== false) {
    $content = substr_replace($content, "\n" . $panelsEndAndScript . $closingDivMarker, $lastPos, strlen($closingDivMarker));
}

// Add the patch marker at the end
$content .= "\n" . $patchMarker;

// 4. Write the new content back to the file
if (file_put_contents($targetFile, $content)) {
    echo "SUCCESS: The file {$targetFile} has been patched.\n";
} else {
    die("ERROR: Failed to write patched content to {$targetFile}.\n");
}
