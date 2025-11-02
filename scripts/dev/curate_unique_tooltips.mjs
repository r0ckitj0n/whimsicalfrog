#!/usr/bin/env node
/**
 * curate_unique_tooltips.mjs
 * Generates unique, helpful, snarky tooltips for each element and upserts to DB.
 * Avoids repetitive phrases like "pray to tech gods" by using diverse, contextual copy.
 */

const BASE = process.env.WF_DEV_ORIGIN || 'http://localhost:8080';
const IS_LOCAL = /localhost|127\.0\.0\.1/.test(BASE);
const ADMIN_TOKEN = process.env.WF_ADMIN_TOKEN || 'whimsical_admin_2024';
const CONTEXTS = ['settings','customers','inventory','orders','pos','reports','admin','common','dashboard','marketing'];
const GENERIC_PHRASES = new Set([
  'adjusts a system setting. change thoughtfully, test quickly, and keep receipts.',
  'controls for this feature. click to interact, hover for details.',
]);
const GENERIC_REGEX = [
  /change\s+thoughtfully[^\n]*keep\s+receipts/i,
  /controls\s+for\s+this\s+feature/i,
];
// Force-override patterns: always update these even if non-empty
const FORCE_OVERRIDE_REGEX = [
  /^action:email[-_]?history[-_]?copy[-_]?curl$/i,
  /^action:email[-_]?history[-_]?copy[-_]?headers$/i,
  /^action:email[-_]?history[-_]?copy[-_]?subject$/i,
  /^action:email[-_]?history[-_]?copy[-_]?to$/i,
  /^action:email[-_]?history[-_]?copy[-_]?type$/i,
  /^email[-_]?history[-_]?copy[-_]?curl$/i,
  /^email[-_]?history[-_]?copy[-_]?headers$/i,
  /^email[-_]?history[-_]?copy[-_]?subject$/i,
  /^email[-_]?history[-_]?copy[-_]?to$/i,
  /^email[-_]?history[-_]?copy[-_]?type$/i,
];
const DRY_RUN = process.env.DRY_RUN === '1';

const sleep = (ms) => new Promise(r => setTimeout(r, ms));

async function apiGet(url) {
  const headers = { 'Accept': 'application/json' };
  if (IS_LOCAL) headers['X-WF-Dev-Admin'] = '1';
  const res = await fetch(url, { headers });
  if (!res.ok) throw new Error(`GET ${url} => ${res.status}`);
  return await res.json();
}

async function apiPost(url, data) {
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  if (IS_LOCAL) headers['X-WF-Dev-Admin'] = '1';
  const res = await fetch(url, {
    method: 'POST',
    headers,
    body: JSON.stringify({ ...data, admin_token: ADMIN_TOKEN })
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
    // Admin nav tabs (unique copy per tab)
    '^admindashboardtab$': [
      `Your empire at a glance. Try not to panic.`
    ],
    '^admincustomerstab$': [
      `Treat them well. They have the money.`
    ],
    '^admininventorytab$': [
      `Where SKUs go to multiply when you’re not looking.`
    ],
    '^adminorderstab$': [
      `Organize chaos into packages.`
    ],
    '^adminpostab$': [
      `Point-of-Sale, not the other POS you were thinking.`
    ],
    '^adminreportstab$': [
      `Because spreadsheets are a love language.`
    ],
    '^adminmarketingtab$': [
      `Convince strangers to love your brand. No pressure.`
    ],
    '^adminsettingstab$': [
      `The control room. Flip switches, look important.`
    ],
    // Email History controls (distinct content per action)
    'email[-_]?history[-_]?next': [
      `Go to newer entries. Because yesterday’s emails are so yesterday.`
    ],
    'email[-_]?history[-_]?prev': [
      `Back to older entries. Archaeology, but for outbox drama.`
    ],
    'email[-_]?history[-_]?toggle[-_]?json': [
      `Show/hide raw JSON logs. Nerd goggles on, nerd goggles off.`
    ],
    '(?:open[-_]?email[-_]?history|emailhistorybtn)': [
      `Open the email activity log. Proof your messages actually left the building.`
    ],
    // Email History copy-* actions
    '^email[-_]?history[-_]?copy[-_]?curl$': [
      `Copy a ready-to-run curl command. For when receipts need command lines.`,
    ],
    '^email[-_]?history[-_]?copy[-_]?headers$': [
      `Copy raw headers. CSI: Inbox, featuring you as the forensic analyst.`,
    ],
    '^email[-_]?history[-_]?copy[-_]?subject$': [
      `Copy the subject line. Evidence for A/B tests and gentle shaming.`,
    ],
    '^email[-_]?history[-_]?copy[-_]?to$': [
      `Copy the recipient address. Because typos are surprisingly expensive.`,
    ],
    '^email[-_]?history[-_]?copy[-_]?type$': [
      `Copy the message type. Humans say "kind"; APIs say "type"—we translate.`,
    ],
    // Inventory actions
    '^refresh[-_]?categories$': [ `When in doubt, mash refresh. It fixes everything... eventually.` ],
    '^delete[-_]?item$': [ `Send this SKU to the big warehouse in the sky.` ],
    '^navigate[-_]?item$': [ `Because scrolling is passé.` ],
    '^open[-_]?cost[-_]?modal$': [ `Crunch the numbers so the numbers don't crunch you.` ],
    '^save[-_]?cost[-_]?item$': [ `Commit your math to the database like you mean it.` ],
    '^delete[-_]?cost[-_]?item$': [ `Goodbye, questionable expense.` ],
    '^clear[-_]?cost[-_]?breakdown$': [ `Nuke it from orbit—it’s the only way to be sure.` ],
    '^get[-_]?cost[-_]?suggestion$': [ `Ask the machine what it thinks money is.` ],
    '^get[-_]?price[-_]?suggestion$': [ `Robot says charge more. You probably should.` ],
    '^apply[-_]?price[-_]?suggestion$': [ `Trust the algorithm. What could go wrong?` ],
    '^open[-_]?marketing[-_]?manager$': [ `Outsource creativity to silicon.` ],
    '^generate[-_]?marketing[-_]?copy$': [ `AI-generated enthusiasm—now with fewer typos.` ],
    '^process[-_]?images[-_]?ai$': [ `Let AI crop everything because rectangles are hard.` ],
    '^open[-_]?color[-_]?template[-_]?modal$': [ `Because one shade of blue was never enough.` ],
    '^open[-_]?global[-_]?colors[-_]?management$': [ `Centralize your rainbow like a responsible adult.` ],
    // Orders
    '^filter[-_]?orders$': [ `Sift the chaos until only the good stuff remains.` ],
    '^refund[-_]?order$': [ `Be the hero customers deserve. Or the villain accountants fear.` ],
    '^mark[-_]?shipped$': [ `Declare it shipped like a captain christening a boat.` ],
    // Customers
    '^search[-_]?customers$': [ `Hunt down that one email from 2018.` ],
    '^add[-_]?customer$': [ `Create a new friend who owes you money.` ],
    '^merge[-_]?customers$': [ `Two become one. Data harmony achieved.` ],
    // Reports
    '^export[-_]?report$': [ `Download a spreadsheet to ignore later.` ],
    '^print[-_]?report$': [ `Feed your printer and your soul with paper charts.` ],
    '^change[-_]?range$': [ `Move the goalposts until the numbers look nice.` ],
    // Marketing
    '^create[-_]?campaign$': [ `Announce your existence to the disinterested masses.` ],
    '^send[-_]?test[-_]?email$': [ `Email yourself like it's 2005.` ],
    '^generate[-_]?coupons$': [ `Because nothing sells like a discount and a countdown.` ],
    // POS
    '^toggle[-_]?fullscreen$': [ `Make the buttons comically large for dramatic effect.` ],
    '^exit[-_]?pos$': [ `Return to the world of tiny buttons and even tinier margins.` ],
    '^browse[-_]?items$': [ `Window-shopping, but with fewer windows.` ],
    '^checkout$': [ `Turn tapping into revenue. Cha-ching.` ],
    // DB tools
    '^runcommand$': [ `Push mysterious buttons and hope for green checkmarks.` ],
    '^generate[-_]?css$': [ `Birth fresh styles from the command line.` ],
    '^test[-_]?css$': [ `Make sure you didn’t invent a new shade of broken.` ],
    // DB Web Manager
    '^showtab$': [ `Tabs: because one panel is never enough.` ],
    '^executequery$': [ `Summon data with ancient SQL incantations.` ],
    '^clearquery$': [ `Erase your brilliance. Regret immediately.` ],
    '^loadtables$': [ `Count your tables like sheep.` ],
    '^describetable$': [ `Peek under the hood without getting greasy.` ],
    // Room Config / Cost Breakdown
    '^resetform$': [ `Ctrl+Z for your entire taste in design.` ],
    '^apply[-_]?suggested[-_]?cost[-_]?to[-_]?cost[-_]?field$': [ `Let the computer guess and pretend it was your idea.` ],
    // Save actions (5 variants)
    'save': [
      `Commits your changes to the database. Like a seatbelt for settings—click it before things get bumpy.`,
      `Writes updates and makes them stick. Drafts are for novels, not admin panels.`,
      `Locks in your edits. If future-you disagrees, future-you can change it back.`,
      `Stores your work so it survives page refreshes and existential crises.`,
      `Persists changes. Small, frequent saves beat heroic rescues every time.`
    ],
    // Cancel/Close actions (5 variants)
    'cancel|close': [
      `Closes without saving. Exit stage left—props stay where they were.`,
      `Backs out gracefully. Changes that didn't meet the Save button won't be invited.`,
      `Dismisses the dialog. No hard feelings, no saved changes.`,
      `Shuts this down without committing. A clean exit is still progress.`,
      `Closes the panel and carries on. Nothing saved, nothing broken.`
    ],
    // Delete actions (5 variants)
    'delete|remove': [
      `Removes permanently. Gone means gone—triple-check before clicking.`,
      `Erases the selected item. This is the nuclear option—use with respect.`,
      `Deletes forever. If your stomach fluttered, pause and double-check.`,
      `Permanent removal. Consider accounting before wielding this power.`,
      `Removes the target. No undo, no "just kidding," no ctrl+Z to save you.`
    ],
    // Test actions (5 variants)
    'test': [
      `Checks your setup. Green lights mean proceed; red means tea, then fixes.`,
      `Pings the service with your settings. If it responds, we're friends.`,
      `Runs diagnostics. Better to find issues here than in front of customers.`,
      `Validates configuration. Two minutes now saves two hours later.`,
      `Sends a test signal. If it answers with wisdom, you're set.`
    ],
    // Import actions (5 variants)
    'import': [
      `Brings external data in. Check your columns—spreadsheets are unforgiving.`,
      `Loads data from a file. Backup first. Adventure second.`,
      `Imports from CSV. Templates exist so headaches don't.`,
      `Uploads data in bulk. One wrong mapping and you've renamed 500 products to "undefined."`,
      `Feeds the system a file. Well-labeled CSVs become products; chaos becomes regret.`
    ],
    // Export actions (5 variants)
    'export|download': [
      `Sends data to a file. Accountability in CSV form.`,
      `Downloads a clean export. Future you loves this habit.`,
      `Exports to CSV for backups or analysis. Data travels well.`,
      `Grabs your data before the universe tests your backup strategy.`,
      `Generates a file. Keep it safe; you'll thank yourself later.`
    ],
    // Preview actions (5 variants)
    'preview': [
      `Shows how changes will look. Try before you buy—no commitment.`,
      `Displays a preview. Two seconds here saves ten minutes later.`,
      `Renders a preview so you can spot issues before they go live.`,
      `Shows the result. Peek, adjust, repeat until it's right.`,
      `Previews your work. Confidence beats guesswork.`
    ],
    // Duplicate actions (5 variants)
    'duplicate|copy|clone': [
      `Clones the selected item. Because doing it twice by hand is a hobby, not a workflow.`,
      `Makes a copy so you can tweak without breaking the original. Chaos, but contained.`,
      `Duplicates the item. Copy a winner, adjust the details, save time.`,
      `Creates a clone. Smart beats busy every time.`,
      `Copies this thing. Efficient is attractive.`
    ],
    // Reset actions (5 variants)
    'reset|default': [
      `Reverts to defaults. The "oops" button—use sparingly, with beverages.`,
      `Restores factory settings. Back to basics when experiments get enthusiastic.`,
      `Resets everything. No shame in a clean slate.`,
      `Returns to defaults. Sometimes starting over is the fastest path forward.`,
      `Clears customizations. A fresh start for when things got weird.`
    ],
    // Refresh actions (5 variants)
    'refresh|reload': [
      `Reloads the current view. Catch fresh data without page gymnastics.`,
      `Refreshes the list. Because stale data is nobody's friend.`,
      `Pulls the latest. Stay current without full page reloads.`,
      `Updates the display. See what changed without losing your place.`,
      `Syncs with the server. Fresh data, same spot.`
    ],
    // Search actions (5 variants)
    'search|filter': [
      `Type a hint; find the needle. Filters spare your scroll finger.`,
      `Searches the list. A few characters beat endless scrolling.`,
      `Filters results. Find what you need without the treasure hunt.`,
      `Narrows the list. Precision beats pagination.`,
      `Searches by keyword. Your time is valuable; use it.`
    ],
    // Edit actions (5 variants)
    'edit|modify|update': [
      `Opens the editor. Polish the details—typos, images, prices.`,
      `Modifies the selected item. Small fixes, real impact.`,
      `Edits this entry. Change what needs changing, leave the rest.`,
      `Updates the record. Accuracy now prevents confusion later.`,
      `Opens for editing. Make it better, then move on.`
    ],
    // Add/Create actions (5 variants)
    'add|create|new': [
      `Creates a new entry. Name it clearly, fill it completely.`,
      `Adds a fresh item. Start with good data; finish with fewer headaches.`,
      `Creates something new. Clear names now prevent future detective work.`,
      `Adds a new record. Sensible beats cryptic every time.`,
      `Starts a new entry. Pick names a human would admire.`
    ],
    // View actions (5 variants)
    'view|show|display': [
      `Opens the details. All the info in one tidy place.`,
      `Displays the full record. Clarity first, actions second.`,
      `Shows the details. Peek inside without spawning fifteen tabs.`,
      `Opens for viewing. Read-only mode for curious minds.`,
      `Displays the item. Look, learn, decide what's next.`
    ],
    // Print actions (5 variants)
    'print': [
      `Prints the document. Paper still matters sometimes.`,
      `Generates a printable version. Ink and paper, old school.`,
      `Prints a slip so box-time feels calm and correct.`,
      `Sends to printer. Physical backups for the analog world.`,
      `Creates a print-ready version. Trees everywhere hold their breath.`
    ],
    // Sync actions (5 variants)
    'sync|synchronize': [
      `Syncs with the external service. Stay in step without manual labor.`,
      `Pulls updates from the source. Automation beats repetition.`,
      `Synchronizes data. Let the machines do the boring parts.`,
      `Imports fresh data from the service. Coffee recommended for large catalogs.`,
      `Syncs items and updates. Consistency without the copy-paste marathon.`
    ],
    // Clear actions (5 variants)
    'clear': [
      `Clears the field. A blank slate for fresh input.`,
      `Removes stored data. Useful for resets, terrifying for typos.`,
      `Clears the cache. A miniature spa day for performance.`,
      `Empties the current value. Start over without the baggage.`,
      `Resets the field. Clean slate, new start.`
    ],
    // Help actions (5 variants)
    'help|documentation|docs': [
      `Opens contextual help. Quick pointers when you want them; respectful silence when you don't.`,
      `Shows documentation. When guesswork gets old, answers live here.`,
      `Opens the help docs. Faster than pinging a group chat at 11pm.`,
      `Displays guidance. Tips, patterns, and the occasional pep talk.`,
      `Opens help. Because everyone needs a hint sometimes.`
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
  
  // Context-specific multi-variant fallbacks if no pattern matches
  const contextVariantMap = {
    settings: [
      `Tweaks how the system behaves. Make a change, then make it proud.`,
      `Configuration central. Adjust, test, pretend it was always like this.`,
      `Settings switchboard. Flip wisely; results may vary delightfully.`,
      `Tune the knobs until it sings. Bonus points for not breaking anything.`,
      `House rules for the app. Keep it tidy; future-you will cheer.`
    ],
    inventory: [
      `Clean product data means fewer "Where did that SKU go?" moments.`,
      `Curate your catalog like it’s award season. Accuracy wins.`,
      `Your items, but organized. Shoppers love when names make sense.`,
      `Polish titles, prices, and variants until they sparkle.`,
      `Inventory zen: fewer typos, fewer refunds, happier humans.`
    ],
    orders: [
      `Move orders from chaos to shipped-with-a-bow.`,
      `Every click gets a package closer to a mailbox.`,
      `Workflow fuel: pick, pack, ship, sip coffee.`,
      `Keep the pipeline flowing—delays belong to yesterday.`,
      `Turn carts into cardboard victories.`
    ],
    customers: [
      `Treat their data like crown jewels—accurate and secure.`,
      `Make it personal (the good kind). Names right, messages relevant.`,
      `From prospects to superfans—be helpful at every step.`,
      `Keep notes tidy. Future conversations get easier.`,
      `Respect the inbox. Earn replies with clarity.`
    ],
    marketing: [
      `Charm at scale. Honest copy beats gimmicks every time.`,
      `Persuasion toolkit: helpful, timely, and on-brand.`,
      `Announce value, not noise. People notice the difference.`,
      `Give them a reason to click that isn’t just “SALE!!!”.`,
      `Make messages that even you would open.`
    ],
    reports: [
      `Numbers with opinions. Interpret responsibly.`,
      `Let the charts talk. Then act like you listened.`,
      `Trends don’t lie; they do whisper. Pay attention.`,
      `Today’s reality check, in handy graph form.`,
      `Metrics: helpful, if you’re brave enough to look.`
    ],
    pos: [
      `Checkout without drama. Taps in, revenue out.`,
      `Big buttons, fast moves, happy line.`,
      `Make the register proud—smooth and precise.`,
      `Less friction, more receipts.`,
      `POS: Point-of-Sale, peak-of-style.`
    ],
    dashboard: [
      `Your daily pulse check. Celebrate the green bits.`,
      `Widgets with opinions about your business.`,
      `Status at a glance—coffee optional but recommended.`,
      `A tidy overview so you can plan the next victory.`,
      `If it matters, it shows up here first.`
    ],
    admin: [
      `Buttons with authority. Use for good, not chaos.`,
      `Admin tools: sharp, shiny, and powerful.`,
      `This is where the sausage gets made (tastefully).`,
      `Careful clicks, big outcomes.`,
      `Command center vibes. Cape optional.`
    ],
    common: [
      `Does what it says, with a bit of flair.`,
      `Click when ready; results will follow.`,
      `Small control, big convenience.`,
      `A polite button with strong opinions.`,
      `Friendly control. Mostly harmless.`
    ]
  };
  const fallbacks = contextVariantMap[ctx] || [
    `Useful control. Try it—you might like the result.`,
    `A sensible action with questionable humor.`,
    `Button goes click; feature goes whoosh.`,
    `Polite helper standing by for duty.`,
    `Mildly delightful, surprisingly effective.`
  ];
  const fbHash = Array.from(elementId).reduce((h, c) => ((h << 5) - h) + c.charCodeAt(0), 0);
  return fallbacks[Math.abs(fbHash) % fallbacks.length];
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
        const currentNorm = current.toLowerCase();
        const idLower = String(t.element_id || '').toLowerCase();
        const shouldForce = FORCE_OVERRIDE_REGEX.some(r => r.test(idLower));

        // Heuristic: Only overwrite when content is empty or looks generic
        const isEmpty = current.length === 0;
        const isGeneric = GENERIC_PHRASES.has(currentNorm) || GENERIC_REGEX.some(r => r.test(current));
        if (!shouldForce && !isEmpty && !isGeneric) {
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
