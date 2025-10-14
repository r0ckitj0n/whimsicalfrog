#!/usr/bin/env node
/**
 * curate_unique_tooltips.mjs
 * Generates unique, helpful, snarky tooltips for each element and upserts to DB.
 * Avoids repetitive phrases like "pray to tech gods" by using diverse, contextual copy.
 */

const BASE = process.env.WF_DEV_ORIGIN || 'http://localhost:8080';
const CONTEXTS = ['settings','customers','inventory','orders','pos','reports','admin','common','dashboard','marketing'];
const DRY_RUN = process.env.DRY_RUN === '1';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

async function apiGet(url) {
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  if (!res.ok) throw new Error(`GET ${url} => ${res.status}`);
  return await res.json();
}

async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(data)
  });
  if (!res.ok) throw new Error(`POST ${url} => ${res.status}`);
  return await res.json();
}

// Generate unique snarky/helpful tooltip based on element_id and context
function generateUniqueTooltip(elementId, context) {
  const id = String(elementId || '').toLowerCase();
  const ctx = String(context || '').toLowerCase();
  const action = id.startsWith('action:') ? id.slice(7) : id;
  
  // Comprehensive mapping with multiple unique variants per pattern
  const patterns = {
    // Save actions (5 variants)
    'save': [
      'Commits your changes to the database. Like a seatbelt for settings—click it before things get bumpy.',
      'Writes updates and makes them stick. Drafts are for novels, not admin panels.',
      'Locks in your edits. If future-you disagrees, future-you can change it back.',
      'Stores your work so it survives page refreshes and existential crises.',
      'Persists changes. Small, frequent saves beat heroic rescues every time.'
    ],
    // Cancel/Close actions (5 variants)
    'cancel|close': [
      'Closes without saving. Exit stage left—props stay where they were.',
      'Backs out gracefully. Changes that didn't meet the Save button won't be invited.',
      'Dismisses the dialog. No hard feelings, no saved changes.',
      'Shuts this down without committing. A clean exit is still progress.',
      'Closes the panel and carries on. Nothing saved, nothing broken.'
    ],
    // Delete actions (5 variants)
    'delete|remove': [
      'Removes permanently. Gone means gone—triple-check before clicking.',
      'Erases the selected item. This is the nuclear option—use with respect.',
      'Deletes forever. If your stomach fluttered, pause and double-check.',
      'Permanent removal. Consider accounting before wielding this power.',
      'Removes the target. No undo, no "just kidding," no ctrl+Z to save you.'
    ],
    // Test actions (5 variants)
    'test': [
      'Checks your setup. Green lights mean proceed; red means tea, then fixes.',
      'Pings the service with your settings. If it responds, we're friends.',
      'Runs diagnostics. Better to find issues here than in front of customers.',
      'Validates configuration. Two minutes now saves two hours later.',
      'Sends a test signal. If it answers with wisdom, you're set.'
    ],
    // Import actions (5 variants)
    'import': [
      'Brings external data in. Check your columns—spreadsheets are unforgiving.',
      'Loads data from a file. Backup first. Adventure second.',
      'Imports from CSV. Templates exist so headaches don't.',
      'Uploads data in bulk. One wrong mapping and you've renamed 500 products to "undefined."',
      'Feeds the system a file. Well-labeled CSVs become products; chaos becomes regret.'
    ],
    // Export actions (5 variants)
    'export|download': [
      'Sends data to a file. Accountability in CSV form.',
      'Downloads a clean export. Future you loves this habit.',
      'Exports to CSV for backups or analysis. Data travels well.',
      'Grabs your data before the universe tests your backup strategy.',
      'Generates a file. Keep it safe; you'll thank yourself later.'
    ],
    // Preview actions (5 variants)
    'preview': [
      'Shows how changes will look. Try before you buy—no commitment.',
      'Displays a preview. Two seconds here saves ten minutes later.',
      'Renders a preview so you can spot issues before they go live.',
      'Shows the result. Peek, adjust, repeat until it's right.',
      'Previews your work. Confidence beats guesswork.'
    ],
    // Duplicate actions (5 variants)
    'duplicate|copy|clone': [
      'Clones the selected item. Because doing it twice by hand is a hobby, not a workflow.',
      'Makes a copy so you can tweak without breaking the original. Chaos, but contained.',
      'Duplicates the item. Copy a winner, adjust the details, save time.',
      'Creates a clone. Smart beats busy every time.',
      'Copies this thing. Efficient is attractive.'
    ],
    // Reset actions (5 variants)
    'reset|default': [
      'Reverts to defaults. The "oops" button—use sparingly, with beverages.',
      'Restores factory settings. Back to basics when experiments get enthusiastic.',
      'Resets everything. No shame in a clean slate.',
      'Returns to defaults. Sometimes starting over is the fastest path forward.',
      'Clears customizations. A fresh start for when things got weird.'
    ],
    // Refresh actions (5 variants)
    'refresh|reload': [
      'Reloads the current view. Catch fresh data without page gymnastics.',
      'Refreshes the list. Because stale data is nobody's friend.',
      'Pulls the latest. Stay current without full page reloads.',
      'Updates the display. See what changed without losing your place.',
      'Syncs with the server. Fresh data, same spot.'
    ],
    // Search actions (5 variants)
    'search|filter': [
      'Type a hint; find the needle. Filters spare your scroll finger.',
      'Searches the list. A few characters beat endless scrolling.',
      'Filters results. Find what you need without the treasure hunt.',
      'Narrows the list. Precision beats pagination.',
      'Searches by keyword. Your time is valuable; use it.'
    ],
    // Edit actions (5 variants)
    'edit|modify|update': [
      'Opens the editor. Polish the details—typos, images, prices.',
      'Modifies the selected item. Small fixes, real impact.',
      'Edits this entry. Change what needs changing, leave the rest.',
      'Updates the record. Accuracy now prevents confusion later.',
      'Opens for editing. Make it better, then move on.'
    ],
    // Add/Create actions (5 variants)
    'add|create|new': [
      'Creates a new entry. Name it clearly, fill it completely.',
      'Adds a fresh item. Start with good data; finish with fewer headaches.',
      'Creates something new. Clear names now prevent future detective work.',
      'Adds a new record. Sensible beats cryptic every time.',
      'Starts a new entry. Pick names a human would admire.'
    ],
    // View actions (5 variants)
    'view|show|display': [
      'Opens the details. All the info in one tidy place.',
      'Displays the full record. Clarity first, actions second.',
      'Shows the details. Peek inside without spawning fifteen tabs.',
      'Opens for viewing. Read-only mode for curious minds.',
      'Displays the item. Look, learn, decide what's next.'
    ],
    // Print actions (5 variants)
    'print': [
      'Prints the document. Paper still matters sometimes.',
      'Generates a printable version. Ink and paper, old school.',
      'Prints a slip so box-time feels calm and correct.',
      'Sends to printer. Physical backups for the analog world.',
      'Creates a print-ready version. Trees everywhere hold their breath.'
    ],
    // Sync actions (5 variants)
    'sync|synchronize': [
      'Syncs with the external service. Stay in step without manual labor.',
      'Pulls updates from the source. Automation beats repetition.',
      'Synchronizes data. Let the machines do the boring parts.',
      'Imports fresh data from the service. Coffee recommended for large catalogs.',
      'Syncs items and updates. Consistency without the copy-paste marathon.'
    ],
    // Clear actions (5 variants)
    'clear': [
      'Clears the field. A blank slate for fresh input.',
      'Removes stored data. Useful for resets, terrifying for typos.',
      'Clears the cache. A miniature spa day for performance.',
      'Empties the current value. Start over without the baggage.',
      'Resets the field. Clean slate, new start.'
    ],
    // Help actions (5 variants)
    'help|documentation|docs': [
      'Opens contextual help. Quick pointers when you want them; respectful silence when you don't.',
      'Shows documentation. When guesswork gets old, answers live here.',
      'Opens the help docs. Faster than pinging a group chat at 11pm.',
      'Displays guidance. Tips, patterns, and the occasional pep talk.',
      'Opens help. Because everyone needs a hint sometimes.'
    ]
  };
  
  // Find matching pattern and pick variant based on hash
  for (const [pattern, options] of Object.entries(patterns)) {
    const regex = new RegExp(pattern, 'i');
    if (regex.test(action)) {
      const hash = Array.from(elementId).reduce((h, c) => ((h << 5) - h) + c.charCodeAt(0), 0);
      return options[Math.abs(hash) % options.length];
    }
  }
  
  // Context-specific defaults if no pattern matches
  const contextDefaults = {
    settings: 'Adjusts a system setting. Change thoughtfully, test quickly, and keep receipts.',
    inventory: 'Manages product data. Keep it accurate so shoppers see what's real.',
    orders: 'Handles order processing. Make it smooth, make it fast, make it feel effortless.',
    customers: 'Manages customer data. Treat their info with respect and accuracy.',
    marketing: 'Controls marketing tools. Be helpful, be genuine, be strategic.',
    reports: 'Shows business metrics. The numbers don't lie, but they might hurt your feelings.',
    pos: 'Point-of-sale control. In-person checkout that behaves.',
    dashboard: 'Dashboard widget. Your daily pulse check on what matters.',
    admin: 'Admin control. Hover around—each button has a job and an attitude.',
    common: 'Common control. Does exactly what the label says, with minimal drama.'
  };
  
  return contextDefaults[ctx] || 'Controls for this feature. Click to interact, hover for details.';
}

(async () => {
  console.log(`[Tooltip Curation] Starting ${DRY_RUN ? '(DRY RUN)' : '(LIVE)'}`);
  console.log(`[Tooltip Curation] Base: ${BASE}`);
  
  let scanned = 0, updated = 0, skipped = 0, errors = 0;
  const changes = [];
  
  for (const ctx of CONTEXTS) {
    try {
      const res = await apiGet(`${BASE}/api/help_tooltips.php?action=get&page_context=${encodeURIComponent(ctx)}`);
      const tooltips = (res && res.success && Array.isArray(res.tooltips)) ? res.tooltips : [];
      
      for (const t of tooltips) {
        scanned++;
        const current = (t.content || '').trim();
        const suggested = generateUniqueTooltip(t.element_id, ctx);
        
        // Skip if content is identical
        if (current === suggested) {
          skipped++;
          continue;
        }
        
        const payload = {
          element_id: t.element_id,
          page_context: ctx,
          title: t.title || '',
          content: suggested,
          position: t.position || 'top',
          is_active: 1
        };
        
        if (DRY_RUN) {
          changes.push({ ctx, id: t.element_id, before: current, after: suggested });
          updated++;
        } else {
          try {
            const upsertRes = await apiPost(`${BASE}/api/help_tooltips.php?action=upsert`, payload);
            if (upsertRes && upsertRes.success) {
              updated++;
              if (updated <= 10) {
                console.log(`✓ ${ctx}:::${t.element_id}`);
              }
            } else {
              errors++;
            }
            await sleep(10);
          } catch (e) {
            errors++;
            console.warn(`✗ ${ctx}:::${t.element_id}:`, e.message);
          }
        }
      }
    } catch (e) {
      errors++;
      console.warn(`[Tooltip Curation] Failed context ${ctx}:`, e.message);
    }
  }
  
  console.log('[Tooltip Curation] Summary:', { scanned, updated, skipped, errors });
  
  if (DRY_RUN && changes.length > 0) {
    console.log('\n[Tooltip Curation] Sample changes (first 10):');
    changes.slice(0, 10).forEach(c => {
      console.log(`\n${c.ctx}:::${c.id}`);
      console.log(`  Before: ${c.before.slice(0, 80)}${c.before.length > 80 ? '...' : ''}`);
      console.log(`  After:  ${c.after.slice(0, 80)}${c.after.length > 80 ? '...' : ''}`);
    });
    console.log('\nRun without DRY_RUN=1 to apply changes.');
  }
  
  process.exit(errors ? 1 : 0);
})();
