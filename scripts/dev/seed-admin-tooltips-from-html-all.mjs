#!/usr/bin/env node
/**
 * Discover and seed missing tooltips across admin pages by scanning HTML
 * - Pages: settings, customers, inventory, orders, reports, marketing, pos, admin (dashboard)
 * - Fetches ?modal=1 when available to reduce chrome
 * - Finds: buttons, links, [role="button"], [data-action], labels (label[for])
 * - Skips items already in DB; seeds only missing
 * - Generates snarky/helpful copy per-context using targeted patterns + fallbacks
 */

const BASE = process.env.WF_BASE_URL || 'http://localhost:8080';
const IS_LOCAL = /localhost|127\.0\.0\.1/.test(BASE);
const API_URL = `${BASE}/api/help_tooltips.php`;
const ADMIN_TOKEN = process.env.WF_ADMIN_TOKEN || 'whimsical_admin_2024';
const args = new Set(process.argv.slice(2));
const APPLY = args.has('--apply');

const CONTEXTS = [
  'settings','customers','inventory','orders','reports','marketing','pos','admin'
];

function pageUrlFor(ctx) {
  const candidates = [
    `${BASE}/sections/admin_${ctx}.php?modal=1`,
    `${BASE}/sections/admin_${ctx}.php`,
    `${BASE}/admin_${ctx}.php?modal=1`,
    `${BASE}/admin_${ctx}.php`,
  ];
  if (ctx === 'admin') {
    candidates.unshift(`${BASE}/sections/admin_dashboard.php?modal=1`, `${BASE}/sections/admin_dashboard.php`);
  }
  if (ctx === 'pos') {
    candidates.unshift(`${BASE}/pos.php`);
  }
  return candidates;
}

async function fetchOk(url, accept = 'text/html') {
  const headers = { 'Accept': accept };
  if (IS_LOCAL) headers['X-WF-Dev-Admin'] = '1';
  try {
    const res = await fetch(url, { headers });
    if (res.ok) return res;
  } catch {}
  return null;
}

async function getPageHtml(ctx) {
  const urls = pageUrlFor(ctx);
  for (const u of urls) {
    const res = await fetchOk(u, 'text/html');
    if (res) return await res.text();
  }
  return '<html></html>';
}

async function getJson(url) {
  const headers = { 'Accept': 'application/json' };
  if (IS_LOCAL) headers['X-WF-Dev-Admin'] = '1';
  const res = await fetch(url, { headers });
  if (!res.ok) throw new Error(`GET ${url} -> ${res.status}`);
  return res.json();
}
async function postJson(url, body) {
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  if (IS_LOCAL) headers['X-WF-Dev-Admin'] = '1';
  const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify({ ...body, admin_token: ADMIN_TOKEN }) });
  if (!res.ok) throw new Error(`POST ${url} -> ${res.status}`);
  return res.json();
}

function unescapeHtml(s='') { return s.replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&amp;/g,'&').replace(/&quot;/g,'"').replace(/&#39;/g,"'"); }
function stripTags(s='') { return unescapeHtml(String(s).replace(/<[^>]+>/g,' ')).replace(/\s+/g,' ').trim(); }
function findAll(regex, text) { const out = []; let m; while ((m = regex.exec(text)) !== null) out.push(m); return out; }

function collectCandidates(html) {
  const out = [];
  // Buttons
  for (const m of findAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/gi, html)) {
    const attrs = m[1] || ''; const inner = m[2] || '';
    const id = (attrs.match(/\bid\s*=\s*"([^"]+)"/i) || [])[1] || (attrs.match(/\bid\s*=\s*'([^']+)'/i) || [])[1] || '';
    const da = (attrs.match(/\bdata-action\s*=\s*"([^"]+)"/i) || [])[1] || (attrs.match(/\bdata-action\s*=\s*'([^']+)'/i) || [])[1] || '';
    const aria = (attrs.match(/\baria-label\s*=\s*"([^"]+)"/i) || [])[1] || (attrs.match(/\baria-label\s*=\s*'([^']+)'/i) || [])[1] || '';
    const title = stripTags(aria || inner);
    const element_id = id || da || '';
    if (!element_id) continue;
    out.push({ element_id, title });
  }
  // Links
  for (const m of findAll(/<a\b([^>]*)>([\s\S]*?)<\/a>/gi, html)) {
    const attrs = m[1] || ''; const inner = m[2] || '';
    const id = (attrs.match(/\bid\s*=\s*"([^"]+)"/i) || [])[1] || (attrs.match(/\bid\s*=\s*'([^']+)'/i) || [])[1] || '';
    const da = (attrs.match(/\bdata-action\s*=\s*"([^"]+)"/i) || [])[1] || (attrs.match(/\bdata-action\s*=\s*'([^']+)'/i) || [])[1] || '';
    const aria = (attrs.match(/\baria-label\s*=\s*"([^"]+)"/i) || [])[1] || (attrs.match(/\baria-label\s*=\s*'([^']+)'/i) || [])[1] || '';
    const title = stripTags(aria || inner);
    const element_id = id || da || '';
    if (!element_id) continue;
    out.push({ element_id, title });
  }
  // Generic [data-action]
  for (const m of findAll(/<([a-z0-9:-]+)\b([^>]*)>/gi, html)) {
    const attrs = m[2] || '';
    const da = (attrs.match(/\bdata-action\s*=\s*"([^"]+)"/i) || [])[1] || (attrs.match(/\bdata-action\s*=\s*'([^']+)'/i) || [])[1] || '';
    if (!da) continue;
    out.push({ element_id: da, title: da });
  }
  // Labels
  for (const m of findAll(/<label\b([^>]*)>([\s\S]*?)<\/label>/gi, html)) {
    const attrs = m[1] || ''; const inner = m[2] || '';
    const forId = (attrs.match(/\bfor\s*=\s*"([^"]+)"/i) || [])[1] || (attrs.match(/\bfor\s*=\s*'([^']+)'/i) || [])[1] || '';
    if (!forId) continue;
    const element_id = `label[for="${forId}"]`;
    const title = stripTags(inner) || forId;
    out.push({ element_id, title });
  }
  // De-duplicate by element_id
  const seen = new Set();
  return out.filter(x => { if (seen.has(x.element_id)) return false; seen.add(x.element_id); return true; });
}

function generateCopy(elementId, ctx) {
  const id = String(elementId || '').toLowerCase();
  const targeted = [
    // Admin tabs
    [/^admindashboardtab$/, `Your empire at a glance. Try not to panic.`],
    [/^admincustomerstab$/, `Treat them well. They have the money.`],
    [/^admininventorytab$/, `Where SKUs go to multiply when you’re not looking.`],
    [/^adminorderstab$/, `Organize chaos into packages.`],
    [/^adminpostab$/, `Point-of-Sale, not the other POS you were thinking.`],
    [/^adminreportstab$/, `Because spreadsheets are a love language.`],
    [/^adminmarketingtab$/, `Convince strangers to love your brand. No pressure.`],
    [/^adminsettingstab$/, `The control room. Flip switches, look important.`],
    // Email history
    [/^open[-_]?email[-_]?history|emailhistorybtn/, `Open the email activity log. Proof your messages actually left the building.`],
    // Settings visual/design
    [/^open[-_]?room[-_]?map[-_]?manager$/, `Manage and apply room maps. Align click zones without arranging a single piece of furniture.`],
    [/^open[-_]?action[-_]?icons[-_]?manager$|^actioniconsmanagerbtn$/, `Customize admin buttons and icons so your clicks look as good as they feel.`],
    [/^open[-_]?colors[-_]?fonts$/, `Brand colors and type, unified. Pick your palette and typography like you mean it.`],
    // Inventory
    [/^refresh[-_]?categories$/, `When in doubt, mash refresh. It fixes everything... eventually.`],
    [/^delete[-_]?item$/, `Send this SKU to the big warehouse in the sky.`],
    [/^navigate[-_]?item$/, `Because scrolling is passé.`],
    [/^open[-_]?cost[-_]?modal$/, `Crunch the numbers so the numbers don't crunch you.`],
    [/^save[-_]?cost[-_]?item$/, `Commit your math to the database like you mean it.`],
    [/^delete[-_]?cost[-_]?item$/, `Goodbye, questionable expense.`],
    [/^clear[-_]?cost[-_]?breakdown$/, `Nuke it from orbit—it’s the only way to be sure.`],
    [/^get[-_]?cost[-_]?suggestion$/, `Ask the machine what it thinks money is.`],
    [/^get[-_]?price[-_]?suggestion$/, `Robot says charge more. You probably should.`],
    [/^apply[-_]?price[-_]?suggestion$/, `Trust the algorithm. What could go wrong?`],
    [/^open[-_]?marketing[-_]?manager$/, `Outsource creativity to silicon.`],
    [/^generate[-_]?marketing[-_]?copy$/, `AI-generated enthusiasm—now with fewer typos.`],
    [/^process[-_]?images[-_]?ai$/, `Let AI crop everything because rectangles are hard.`],
    // Orders
    [/^filter[-_]?orders$/, `Sift the chaos until only the good stuff remains.`],
    [/^refund[-_]?order$/, `Be the hero customers deserve. Or the villain accountants fear.`],
    [/^mark[-_]?shipped$/, `Declare it shipped like a captain christening a boat.`],
    // Customers
    [/^search[-_]?customers$/, `Hunt down that one email from 2018.`],
    [/^add[-_]?customer$/, `Create a new friend who owes you money.`],
    [/^merge[-_]?customers$/, `Two become one. Data harmony achieved.`],
    // Reports
    [/^export[-_]?report$/, `Download a spreadsheet to ignore later.`],
    [/^print[-_]?report$/, `Feed your printer and your soul with paper charts.`],
    [/^change[-_]?range$/, `Move the goalposts until the numbers look nice.`],
    // Marketing
    [/^create[-_]?campaign$/, `Announce your existence to the disinterested masses.`],
    [/^send[-_]?test[-_]?email$/, `Email yourself like it's 2005.`],
    [/^generate[-_]?coupons$/, `Because nothing sells like a discount and a countdown.`],
    // POS
    [/^toggle[-_]?fullscreen$/, `Make the buttons comically large for dramatic effect.`],
    [/^exit[-_]?pos$/, `Return to the world of tiny buttons and even tinier margins.`],
    [/^browse[-_]?items$/, `Window-shopping, but with fewer windows.`],
    [/^checkout$/, `Turn tapping into revenue. Cha-ching.`],
  ];
  for (const [re, text] of targeted) if (re.test(id)) return text;
  // Context fallbacks (non-generic variants)
  const fb = {
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
    admin: [
      `Buttons with authority. Use for good, not chaos.`,
      `Admin tools: sharp, shiny, and powerful.`,
      `This is where the sausage gets made (tastefully).`,
      `Careful clicks, big outcomes.`,
      `Command center vibes. Cape optional.`
    ],
  };
  const arr = fb[ctx] || fb.settings;
  const h = Array.from(id).reduce((a,c)=>((a<<5)-a)+c.charCodeAt(0),0);
  return arr[Math.abs(h)%arr.length];
}

async function scanAndPlan(ctx) {
  const html = await getPageHtml(ctx);
  // Detect unauthenticated/login gate; skip seeding this context to avoid polluting with login tooltips
  const looksLikeLogin = /name=["']password["']|label[^>]*for=["']username["']|data-action=["']open-login-modal["']|id=["']loginButton["']/i.test(html);
  if (looksLikeLogin) {
    return { ctx, candidates: 0, existing: 0, missing: 0, planned: [], skippedAuth: true };
  }
  const candidates = collectCandidates(html);
  const current = await getJson(`${API_URL}?action=get&page_context=${encodeURIComponent(ctx)}`).catch(()=>({ success:true, tooltips:[] }));
  const existing = new Set((current.tooltips || []).map(t => String(t.element_id)));

  // Avoid noisy admin help controls
  const blacklist = new Set(['adminHelpToggleBtn','adminHelpDocsBtn','adminHelpDocsLink']);
  const planned = candidates
    .filter(c => !!c.element_id)
    .filter(c => !blacklist.has(c.element_id))
    .filter(c => !existing.has(String(c.element_id)))
    // Filter out unstable/template or random code-like IDs
    .filter(c => {
      const id = String(c.element_id);
      if (id === '{{id}}') return false;
      if (/^label\[for=/.test(id)) return true;
      if (/^action:/.test(id)) return true;
      if (/^[a-z][a-z0-9_-]+$/i.test(id)) return true; // readable ids
      if (/^[a-z]+[A-Za-z0-9]*$/.test(id)) return true; // camel-ish
      // Skip all-caps/digit blocks like 94F28P09
      if (/^[A-Z0-9]{6,}$/.test(id)) return false;
      // Admin dashboard sometimes contains grid cell IDs; only allow action-like names in admin context
      if (ctx === 'admin') {
        const allow = /^(action:)?(open|toggle|print|export|view|run|create|send|mark|refund|filter|browse|checkout|apply|save|test|sync|clear|close|next|prev|download|copy|describe|execute|load|show|reset|refresh|add|delete|merge|edit|email|generate|import|assign|link|map|manager|config|settings|help|reports|orders|customers|inventory|marketing|pos|dashboard)[-_]/i;
        if (!allow.test(id)) return false;
      }
      return false;
    })
    .map(c => ({ element_id: c.element_id, page_context: ctx, title: c.title || c.element_id, content: generateCopy(c.element_id, ctx), position: 'top', is_active: 1 }));
  return { ctx, candidates: candidates.length, existing: existing.size, missing: planned.length, planned };
}

async function main() {
  const results = [];
  for (const ctx of CONTEXTS) {
    try { results.push(await scanAndPlan(ctx)); } catch { results.push({ ctx, candidates:0, existing:0, missing:0, planned:[] }); }
  }
  const totalMissing = results.reduce((a,r)=>a+r.missing,0);
  console.log('[seed-admin-html-all] Summary:', results.map(r => ({ ctx:r.ctx, candidates:r.candidates, existing:r.existing, missing:r.missing })));
  if (!APPLY) {
    for (const r of results) {
      if (!r.missing) continue;
      console.log(`Missing in ${r.ctx}: ${r.missing}`);
      for (const p of r.planned.slice(0, 40)) console.log(` - [${r.ctx}] ${p.element_id}: ${p.title}`);
    }
    console.log(`Total missing across contexts: ${totalMissing}`);
    console.log('Run with --apply to create.');
    return;
  }

  let upserted = 0, errors = 0;
  for (const r of results) {
    for (const p of r.planned) {
      try { const resp = await postJson(`${API_URL}?action=upsert`, p); if (resp && resp.success) upserted++; else errors++; } catch { errors++; }
    }
  }
  console.log(`[seed-admin-html-all] Upserted ${upserted}/${totalMissing}. Errors: ${errors}`);
}

main().catch(err => { console.error('[seed-admin-html-all] Fatal:', err); process.exit(1); });
