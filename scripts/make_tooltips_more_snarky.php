<?php
/**
 * Enhance existing tooltips to be MORE verbose, helpful, and delightfully snarky.
 * Reads current content from DB, expands it, writes it back.
 */
require_once __DIR__ . '/../api/config.php';

$contexts = ["settings","inventory","orders","customers","marketing","reports","admin","common","dashboard","pos"];
$placeholders = implode(',', array_fill(0, count($contexts), '?'));
$q = "SELECT id, page_context, element_id, COALESCE(content,'') AS content
      FROM help_tooltips
      WHERE is_active = 1 AND page_context IN ($placeholders)
      ORDER BY page_context, element_id";
$rows = Database::queryAll($q, $contexts);

function enhanceTooltip(string $current, string $elementId, string $context): string {
  // Skip if already very long (>450 chars) - probably already enhanced
  if (strlen($current) > 450) {
    return $current;
  }
  
  // Skip if already contains snarky markers
  $markers = ['literally', 'spoiler:', 'Pro tip:', 'honestly', 'Let\'s be real', 'probably', 'Trust me'];
  foreach ($markers as $marker) {
    if (stripos($current, $marker) !== false) {
      return $current;
    }
  }
  
  $enhanced = $current;
  
  // Add button-specific wisdom
  if (stripos($elementId, 'Btn') !== false || stripos($elementId, 'button') !== false) {
    if (stripos($elementId, 'save') !== false) {
      $enhanced .= ' Click it like you mean it, then breathe that sigh of relief when the green checkmark appears. Save early, save often, save before you do something you\'ll regret. Your future self is counting on present you to not mess this up.';
    } elseif (stripos($elementId, 'delete') !== false) {
      $enhanced .= ' This is the nuclear option—once you click, it\'s gone forever. No undo, no "just kidding," no ctrl+Z to save you from yourself. Triple-check you\'re deleting the RIGHT thing, not just A thing. We\'ve all been there. Don\'t be there.';
    } elseif (stripos($elementId, 'test') !== false) {
      $enhanced .= ' Run the test, read the results, fix what\'s broken, repeat until everything is green and happy. Testing in production is technically a lifestyle choice, but not a good one. Do it here first where mistakes are cheap and fixable.';
    } elseif (stripos($elementId, 'export') !== false) {
      $enhanced .= ' Download your data before the universe decides to test your backup strategy the hard way. Future you will either thank present you profusely or wonder why you didn\'t do this sooner. Spoiler: do it now.';
    } elseif (stripos($elementId, 'import') !== false) {
      $enhanced .= ' Check your CSV formatting twice, import once. Bulk mistakes are the WORST kind of mistakes because they\'re bulk. One wrong column mapping and you\'ve just renamed 500 products to "undefined." Ask me how I know.';
    } else {
      $enhanced .= ' Click with confidence, or at least fake it convincingly enough that nobody asks uncomfortable questions about whether you know what you\'re doing.';
    }
  }
  
  // Add input-specific guidance
  elseif (stripos($elementId, 'Input') !== false || stripos($elementId, 'Field') !== false) {
    $enhanced .= ' Type carefully—autocorrect is NOT your friend here, and typos in data fields have a sneaky way of haunting you months later when you\'re trying to figure out why reports look weird. Precision now saves headaches later.';
  }
  
  // Add modal/drawer wisdom
  elseif (stripos($elementId, 'Modal') !== false || stripos($elementId, 'Drawer') !== false) {
    $enhanced .= ' This opens in a focused window so you can edit without distractions (or at least fewer distractions). Close it when you\'re done, or leave it open and confuse yourself later when you forget what you were doing. We don\'t judge.';
  }
  
  // Add data display wisdom
  elseif (stripos($elementId, 'Card') !== false || stripos($elementId, 'Table') !== false || stripos($elementId, 'Chart') !== false) {
    $enhanced .= ' The numbers don\'t lie, but they might hurt your feelings. Look at them anyway—data-driven decisions beat gut feelings every single time, even when the data is inconvenient or makes you want to hide under your desk.';
  }
  
  // Add context-specific flavor
  if ($context === 'settings') {
    if (stripos($elementId, 'email') !== false || stripos($elementId, 'smtp') !== false) {
      $enhanced .= ' Email settings: where one typo means nobody gets receipts and you don\'t find out until someone complains three days later. Test thoroughly, then test again, then maybe test one more time just to be sure.';
    } elseif (stripos($elementId, 'square') !== false || stripos($elementId, 'payment') !== false) {
      $enhanced .= ' Payment settings: arguably the most important settings in the entire system because MONEY. Get these wrong and checkout breaks. Get them right and revenue flows like a beautiful, digital river straight into your bank account.';
    } else {
      $enhanced .= ' Settings: where small changes have big consequences. Tread carefully, test thoroughly, keep documentation open in another tab, and maybe say a little prayer to the tech gods before clicking save.';
    }
  } elseif ($context === 'inventory') {
    $enhanced .= ' Inventory: the razor-thin line between "in stock" and "whoops, we oversold by 47 units and now everyone is mad." Keep it accurate, keep it updated, keep customers happy. The alternative is not fun for anyone.';
  } elseif ($context === 'orders') {
    $enhanced .= ' Orders: where customer expectations meet operational reality. Make it smooth, make it fast, make it feel effortless even when it\'s absolutely not. They don\'t need to know about the chaos behind the scenes.';
  } elseif ($context === 'customers') {
    $enhanced .= ' Customers: treat their data like you\'d want yours treated—with respect, accuracy, proper backups, and zero weirdness. They trust you with their info. Honor that trust and don\'t be creepy about it.';
  } elseif ($context === 'marketing') {
    $enhanced .= ' Marketing: the delicate art of saying "buy this" without actually saying "buy this." Be helpful, be genuine, be strategic, be human. Spam is for canned meat, not email campaigns. Your unsubscribe rate will thank you.';
  } elseif ($context === 'reports') {
    $enhanced .= ' Reports: truth serum for your business. Sometimes refreshing, sometimes sobering, occasionally devastating, always worth looking at even when you really, really don\'t want to. Knowledge is power, even uncomfortable knowledge.';
  }
  
  return $enhanced;
}

$updated = 0;
$unchanged = 0;
$failed = 0;

foreach ($rows as $r) {
  $current = trim($r['content']);
  if ($current === '') {
    $unchanged++;
    continue;
  }
  
  $enhanced = enhanceTooltip($current, $r['element_id'], $r['page_context']);
  
  if ($enhanced === $current) {
    $unchanged++;
    continue;
  }
  
  try {
    Database::execute(
      "UPDATE help_tooltips SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
      [$enhanced, $r['id']]
    );
    $updated++;
    if ($updated <= 5) {
      echo "✓ Enhanced: {$r['page_context']}:::{$r['element_id']}\n";
    }
  } catch (Exception $e) {
    $failed++;
    fwrite(STDERR, "✗ {$r['page_context']}:::{$r['element_id']} - ".$e->getMessage()."\n");
  }
}

echo "\n[Snark Enhancement] Updated: $updated, Unchanged: $unchanged, Failed: $failed\n";
echo "Tooltips are now significantly more verbose, helpful, and delightfully snarky!\n";
