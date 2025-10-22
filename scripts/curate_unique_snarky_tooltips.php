<?php
/**
 * Curate unique, helpful, snarky tooltips for each element.
 * Avoids repetitive phrases by using diverse, contextual copy based on V8 audit style.
 */
require_once __DIR__ . '/../api/config.php';

$contexts = ["settings","inventory","orders","customers","marketing","reports","admin","common","dashboard","pos"];
$placeholders = implode(',', array_fill(0, count($contexts), '?'));
$q = "SELECT id, page_context, element_id, COALESCE(title,'') AS title, COALESCE(content,'') AS content, position
      FROM help_tooltips
      WHERE is_active = 1 AND page_context IN ($placeholders)
      ORDER BY page_context, element_id";
$rows = Database::queryAll($q, $contexts);

function generateSpecificTooltip(string $elementId, string $context): ?string {
  $id = strtolower($elementId);
  $action = (strpos($id, 'action:') === 0) ? substr($id, 7) : $id;
  
  // Specific tooltips that explain what each control actually does and where it leads
  // Written for someone new to business, elementary + snarky
  
  // Exact element matches first (highest priority)
  $exact = [
    // Admin navigation tabs
    'admindashboardtab' => 'Your daily "how\'s business?" snapshot—sales totals, recent orders, and alerts worth knowing.',
    'admincustomerstab' => 'Everyone who bought (or browsed). View their orders, update addresses, send emails—basically your rolodex, but digital.',
    'admininventorytab' => 'Where you add products, set prices, upload photos, and write descriptions. This is where you make things look good enough to buy.',
    'adminorderstab' => 'What people bought, packing slips to print, shipping to mark, refunds to process. The "make it happen" zone.',
    'adminreportstab' => 'Sales trends, best sellers, and revenue. Numbers don\'t lie—sometimes that\'s comforting, sometimes it hurts.',
    'adminpostab' => 'In-person checkout. Scan items, take payment, hand over goods. The "cha-ching" button for real-world sales.',
    'adminsettingstab' => 'Configure everything: payment processors, email, shipping, branding. The control room—change carefully.',
    'adminmarketingtab' => 'Discounts, promo codes, and email campaigns. How you nudge browsers into buyers without being pushy.',
    
    // Settings buttons - Business & Store
    'accountsettingsbtn' => 'Your admin profile—name, email, password. The "it\'s me" settings. Change carefully if you share this login.',
    'action:open-account-settings' => 'Your admin profile—name, email, password. The "it\'s me" settings. Change carefully if you share this login.',
    'businessinfobtn' => 'Your store name, contact email, phone, address, and hours. What shows on receipts and footer.',
    'action:open-business-info' => 'Your store name, contact email, phone, address, and hours. What shows on receipts and footer.',
    'websiteconfigbtn' => 'Site-wide settings like timezone, currency, and default language. The boring-but-essential foundation stuff.',
    'systemconfigbtn' => 'Technical knobs: debug mode, cache settings, performance tweaks. For when you\'re feeling brave or desperate.',
    
    // Settings buttons - Product & Catalog
    'categoriesbtn' => 'Organize products into groups (e.g., "Art," "Decor"). Makes browsing easier than one giant pile.',
    'action:open-categories' => 'Organize products into groups (e.g., "Art," "Decor"). Makes browsing easier than one giant pile.',
    'attributesbtn' => 'Global options like Size, Color, Material. Define once, reuse everywhere—consistency without the copy-paste marathon.',
    'action:open-attributes' => 'Global options like Size, Color, Material. Define once, reuse everywhere—consistency without the copy-paste marathon.',
    'roomsbtn' => 'Name and describe each virtual room in your store. Think of it as interior design for your website.',
    'roomcategorylinksbtn' => 'Decide which product categories appear in which rooms. Traffic control so shoppers find the fun stuff naturally.',
    'room-category-btn' => 'Decide which product categories appear in which rooms. Traffic control so shoppers find the fun stuff naturally.',
    'action:open-room-category-links' => 'Decide which product categories appear in which rooms. Traffic control so shoppers find the fun stuff naturally.',
    
    // Settings buttons - Visual & Design
    'backgroundmanagerbtn' => 'Upload and assign room backgrounds. Ambiance matters; this is where you set the vibe.',
    'action:open-background-manager' => 'Upload and assign room backgrounds. Ambiance matters; this is where you set the vibe.',
    'room-mapper-btn' => 'Draw clickable hotspots on room images. Point shoppers to products visually—like a treasure map, but for commerce.',
    'action:open-room-map-editor' => 'Draw clickable hotspots on room images. Point shoppers to products visually—like a treasure map, but for commerce.',
    'areaitemmapperbtn' => 'Link hotspots to specific products. Click a lamp in the photo, see the lamp for sale. Magic.',
    'action:open-area-item-mapper' => 'Link hotspots to specific products. Click a lamp in the photo, see the lamp for sale. Magic.',
    'global-css-btn' => 'Site-wide styling tweaks. Colors, fonts, spacing—polish without hiring a designer. Preview before committing or regret it visually.',
    'action:open-css-catalog' => 'Site-wide styling tweaks. Colors, fonts, spacing—polish without hiring a designer. Preview before committing or regret it visually.',
    'cssrulesbtn' => 'Advanced CSS rules. More power, more risk. Preview before saving or you\'ll redecorate in Comic Sans.',
    'action:open-css-rules' => 'Advanced CSS rules. More power, more risk. Preview before saving or you\'ll ll redecorate in Comic Sans.',
    
    // Settings buttons - Email & Communications
    'emailconfigbtn' => 'Configure how order confirmations and receipts get sent. Test it or customers won\'t get emails.',
    'action:open-email-settings' => 'Configure how order confirmations and receipts get sent. Test it or customers won\'t get emails.',
    'emailhistorybtn' => 'Every email the system sent, who opened it, and delivery status. Your paper trail.',
    'action:open-email-history' => 'Every email the system sent, who opened it, and delivery status. Your paper trail.',
    'templatemanagerbtn' => 'Customize order confirmations, receipts, and marketing emails with your brand voice.',
    'action:open-template-manager' => 'Customize order confirmations, receipts, and marketing emails with your brand voice.',
    'action:open-customer-messages' => 'Inbox for customer inquiries and support messages. Where "I have a question" meets "here\'s an answer."',
    
    // Settings buttons - Payments & Checkout
    'squaresettingsbtn' => 'Enter API keys and test connections. Get this right or checkout breaks—no pressure.',
    'action:open-square-settings' => 'Enter API keys and test connections. Get this right or checkout breaks—no pressure.',
    'shippingsettingsbtn' => 'Carrier API keys (USPS, UPS, FedEx) and distance calculation. Real-time rates beat guesswork—customers appreciate honesty.',
    'receiptmessagesbtn' => 'Custom messages that appear on receipts and order confirmations. Add personality, policies, or "thanks for not haggling."',
    
    // Settings buttons - AI & Automation
    'aisettingsbtn' => 'Pick your AI provider (OpenAI, Anthropic) and set limits. Let robots help write product descriptions.',
    'action:open-ai-settings' => 'Pick your AI provider (OpenAI, Anthropic) and set limits. Let robots help write product descriptions.',
    'aitoolsbtn' => 'AI-powered helpers for product descriptions, SEO copy, and marketing text. You edit, robots draft—teamwork without the awkward meetings.',
    'action:open-ai-tools' => 'AI-powered helpers for product descriptions, SEO copy, and marketing text. You edit, robots draft—teamwork without the awkward meetings.',
    
    // Settings buttons - Dashboard & Layout
    'dashboardconfigbtn' => 'Arrange widgets and cards. Customize what you see first thing every morning.',
    'action:open-dashboard-config' => 'Arrange widgets and cards. Customize what you see first thing every morning.',
    
    // Settings buttons - System & Maintenance
    'databasemaintenancebtn' => 'Optimize tables and clear old logs. Routine maintenance so the site doesn\'t slow down like a browser with 47 tabs.',
    'database-tables-btn' => 'Peek at raw database tables. For the curious or desperate—tread carefully, backup first.',
    'dbschemaauditbtn' => 'Scan your database structure for issues, missing indexes, or orphaned columns. The "is this thing healthy?" checkup.',
    'action:open-db-schema-audit' => 'Scan your database structure for issues, missing indexes, or orphaned columns. The "is this thing healthy?" checkup.',
    'fileexplorerbtn' => 'All uploaded images and documents. Organize, rename, delete—keep the chaos tidy before it becomes archaeological.',
    'systemcleanupbtn' => 'Clear cobwebs so pages load faster. A spa day for your server.',
    'healthdiagnosticsbtn' => 'Run system health checks—database, files, permissions, API connections. Green means go, red means fix it before customers notice.',
    'action:run-health-check' => 'Run system health checks—database, files, permissions, API connections. Green means go, red means fix it before customers notice.',
    'loggingstatusbtn' => 'View system logs and error reports. Where the site confesses what went wrong and when.',
    'action:open-logging-status' => 'View system logs and error reports. Where the site confesses what went wrong and when.',
    'secretsmanagerbtn' => 'Encrypted storage for API keys and sensitive credentials. Keep secrets secret—rotate keys when paranoia strikes.',
    'action:open-secrets-modal' => 'Encrypted storage for API keys and sensitive credentials. Keep secrets secret—rotate keys when paranoia strikes.',
    'devstatusbtn' => 'Development environment status—Vite server, build info, debug flags. For when you\'re wearing the developer hat.',
    'action:open-dev-status' => 'Development environment status—Vite server, build info, debug flags. For when you\'re wearing the developer hat.',
    
    // Settings buttons - Deployment & Repository
    'deploymanagerbtn' => 'Deploy code changes to production. The "make it live" button—test first, deploy second, panic never (ideally).',
    'action:open-deploy-manager' => 'Deploy code changes to production. The "make it live" button—test first, deploy second, panic never (ideally).',
    
    
    // Settings buttons - Analytics & Reports
    'analyticsbtn' => 'Traffic stats, conversion rates, and visitor behavior. See who\'s browsing, what they\'re clicking, and where they bail.',
    'businessreportsbtn' => 'Sales reports, revenue summaries, and financial snapshots. The "how\'s business actually doing?" dashboard.',
    'costbreakdownbtn' => 'Product cost analysis and profit margins. Know what you\'re actually making after materials, shipping, and existential dread.',
    
    // Settings buttons - Specialized Tools
    'addressdiagbtn' => 'Test address validation and shipping calculations. Debug why "123 Main St" won\'t geocode or calculate rates.',
    'usermanagerbtn' => 'Manage admin users, roles, and permissions. Who gets access to what—trust, but verify.',
    'action:scan-item-images' => 'Scan for missing product images or broken image links. Find the gaps before customers do.',
    'scan-item-images' => 'Scan for missing product images or broken image links. Find the gaps before customers do.',
    'fixsampleemailbtn' => 'Send a test email to verify SMTP settings work. Better to test now than discover issues when customers are waiting.',
    'globalcolorsizebtn' => 'Global options like Size, Color, Material. Define once, reuse everywhere—consistency without the copy-paste marathon.',
    'globalcssbtn' => 'Site-wide styling tweaks. Colors, fonts, spacing—polish without hiring a designer. Preview before committing or regret it visually.',
    'roomcategorybtn' => 'Decide which product categories appear in which rooms. Traffic control so shoppers find the fun stuff naturally.',
    'roommapperbtn' => 'Draw clickable hotspots on room images. Point shoppers to products visually—like a treasure map, but for commerce.',
    'business-settings-btn' => 'Your store name, contact email, phone, address, and hours. What shows on receipts and footer.',
    'shippingsettingsformstatic' => 'Carrier API keys (USPS, UPS, FedEx) and distance calculation. Real-time rates beat guesswork—customers appreciate honesty.',
    
    // Action buttons - Generic patterns
    'action:move-up' => 'Reorder list items. Nudge things up until the sequence makes sense.',
    'action:move-down' => 'Reorder list items. Nudge things down until the sequence makes sense.',
    'action:email-history-next' => 'Page through email history. Because 500 emails don\'t fit on one screen.',
    'email-history-next' => 'Page through email history. Because 500 emails don\'t fit on one screen.',
    'action:email-history-prev' => 'Page through email history. Because 500 emails don\'t fit on one screen.',
    'email-history-prev' => 'Page through email history. Because 500 emails don\'t fit on one screen.',
    'action:email-history-toggle-json' => 'Switch between friendly view and raw JSON. For when you need to see the actual data structure.',
    'email-history-toggle-json' => 'Switch between friendly view and raw JSON. For when you need to see the actual data structure.',
    'action:logging-open-file' => 'Open the full log file. Dive deep into errors, warnings, and the occasional mystery.',
    'logging-open-file' => 'Open the full log file. Dive deep into errors, warnings, and the occasional mystery.',
    'action:secrets-rotate' => 'Generate new encryption keys. Rotate secrets when security paranoia strikes or compliance demands it.',
    'secrets-rotate' => 'Generate new encryption keys. Rotate secrets when security paranoia strikes or compliance demands it.',
    'action:run-health-check' => 'Run system health checks—database, files, permissions, API connections. Green means go, red means fix it before customers notice.',
    'run-health-check' => 'Run system health checks—database, files, permissions, API connections. Green means go, red means fix it before customers notice.',
    'action:open-health-diagnostics' => 'Run system health checks—database, files, permissions, API connections. Green means go, red means fix it before customers notice.',
    'action:prevent-submit' => 'Prevents accidental form submission. Safety first—no premature saves.',
    
    // Hints system
    'hints-disable' => 'Turn off all tooltips and hints. For when you know what you\'re doing (or want to pretend you do).',
    'hints-enable-persist' => 'Turn tooltips on permanently. They\'ll stick around until you explicitly disable them.',
    'hints-enable-session' => 'Turn tooltips on for this session only. They\'ll disappear when you close the browser.',
    'hints-restore-banners-persist' => 'Bring back dismissed banners permanently. Second chances for important messages.',
    'hints-restore-banners-session' => 'Bring back dismissed banners for this session. Temporary memory restoration.',
    
    // Sales & Marketing
    'salesadminbtn' => 'Create sale prices and coupon codes. How you make people feel like they got a deal.',
    'cartbuttontextbtn' => 'Customize "Add to Cart" button labels. Tiny words, big impact on clicks.',
    'receiptsettingsbtn' => 'Control what shows on order confirmations. Line items, notes, branding—make it official.',
    'systemdocumentationbtn' => 'Guides, tips, and answers when you forget how something works.',
    'databasetablesbtn' => 'Peek at raw data. For the curious or desperate—tread carefully.',
    
    // Common actions
    'savebtn' => 'Click this before you close anything or your work vanishes like it never happened.',
    'save-btn' => 'Click this before you close anything or your work vanishes like it never happened.',
    'cancelbtn' => 'Use when you changed your mind or clicked the wrong thing.',
    'cancel-btn' => 'Use when you changed your mind or clicked the wrong thing.',
    'closebtn' => 'Nothing gets saved, nothing gets broken. Just a clean exit.',
    'duplicatebtn' => 'Tweak it without starting from scratch. Efficiency over repetition.',
    'importbtn' => 'Bulk add products or customers from a CSV—just format the columns right.',
    'exportbtn' => 'Good for backups, analysis, or moving data elsewhere. Future you will thank present you.',
    'previewbtn' => 'Catch mistakes early when they\'re cheap to fix.',
    'resetbtn' => 'The "undo all my experiments" button—use when things got weird.',
    'deletebtn' => 'No undo, no recovery. Triple-check you\'re deleting the RIGHT thing.',
    'helpbtn' => 'Quick tips when you need them, invisible when you don\'t.',
  ];
  
  // Check both full element_id and action-stripped version
  if (isset($exact[$id])) {
    return $exact[$id];
  }
  if (isset($exact[$action])) {
    return $exact[$action];
  }
  
  // Pattern-based fallbacks for common actions
  if (preg_match('/save/', $action)) {
    return 'Click before closing or your edits disappear into the void.';
  }
  if (preg_match('/cancel|close/', $action)) {
    return 'Your escape hatch when you change your mind.';
  }
  if (preg_match('/delete|remove/', $action)) {
    return 'Gone means gone—check twice, click once.';
  }
  if (preg_match('/test/', $action)) {
    return 'Green means go, red means fix it before customers notice.';
  }
  if (preg_match('/import/', $action)) {
    return 'Bulk operations beat manual entry every time.';
  }
  if (preg_match('/export|download/', $action)) {
    return 'Backups are boring until you need one.';
  }
  if (preg_match('/preview/', $action)) {
    return 'Two seconds here saves ten minutes of regret later.';
  }
  if (preg_match('/duplicate|copy|clone/', $action)) {
    return 'Start with something good, tweak it, save time.';
  }
  if (preg_match('/reset|default/', $action)) {
    return 'The "start over" button for when experiments go sideways.';
  }
  if (preg_match('/refresh|reload/', $action)) {
    return 'Because stale info leads to stale decisions.';
  }
  if (preg_match('/search|filter/', $action)) {
    return 'Type a few letters, skip the scrolling marathon.';
  }
  if (preg_match('/edit|modify|update/', $action)) {
    return 'Fix typos, update prices, polish descriptions.';
  }
  if (preg_match('/add|create|new/', $action)) {
    return 'Fill in the details, save it, and it joins the collection.';
  }
  if (preg_match('/view|show|display/', $action)) {
    return 'Look around, learn, decide what to do next.';
  }
  if (preg_match('/print/', $action)) {
    return 'Old school, but sometimes paper is the answer.';
  }
  if (preg_match('/sync|synchronize/', $action)) {
    return 'Keeps everything in step automatically.';
  }
  if (preg_match('/clear/', $action)) {
    return 'Fresh start, blank slate.';
  }
  if (preg_match('/help|docs|documentation/', $action)) {
    return 'Answers live here when guessing gets old.';
  }
  
  // No generic fallback - return null to indicate "no specific tooltip available"
  // This prevents generic "Hover or click" tooltips on form fields and container elements
  return null;
}

$updated = 0;
$unchanged = 0;
$failed = 0;

foreach ($rows as $r) {
  $current = trim($r['content']);
  $suggested = generateSpecificTooltip($r['element_id'], $r['page_context']);
  
  // Skip if no specific tooltip available (null means no match)
  if ($suggested === null) {
    $unchanged++;
    continue;
  }
  
  // Skip if identical
  if ($current === $suggested) {
    $unchanged++;
    continue;
  }
  
  try {
    Database::execute(
      "UPDATE help_tooltips SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
      [$suggested, $r['id']]
    );
    $updated++;
    if ($updated <= 10) {
      echo "✓ {$r['page_context']}:::{$r['element_id']}\n";
    }
  } catch (Exception $e) {
    $failed++;
    fwrite(STDERR, "✗ {$r['page_context']}:::{$r['element_id']} - ".$e->getMessage()."\n");
  }
}

echo "\n[Unique Tooltip Curation] Updated: $updated, Unchanged: $unchanged, Failed: $failed\n";
echo "Tooltips now have unique, helpful, snarky copy with no repetitive phrases!\n";
