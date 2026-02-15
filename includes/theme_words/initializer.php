<?php
/**
 * Theme Words Table Initializer
 */

function ensure_theme_words_tables(PDO $db): void
{
  $sqlWords = "CREATE TABLE IF NOT EXISTS theme_words (
      id INT NOT NULL AUTO_INCREMENT,
      base_word VARCHAR(128) NOT NULL,
      category VARCHAR(64) NULL DEFAULT 'General',
      definition TEXT NULL,
      tags VARCHAR(255) NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      usage_count INT NOT NULL DEFAULT 0,
      last_used_at TIMESTAMP NULL,
      daily_usage_count INT NOT NULL DEFAULT 0,
      daily_usage_date DATE NULL,
      max_usage_total INT NULL,
      max_usage_per_day INT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_base_word (base_word)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

  $sqlVariants = "CREATE TABLE IF NOT EXISTS theme_word_variants (
      id INT NOT NULL AUTO_INCREMENT,
      theme_word_id INT NOT NULL,
      variant_text VARCHAR(255) NOT NULL,
      definition TEXT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      sort_order INT NOT NULL DEFAULT 0,
      usage_count INT NOT NULL DEFAULT 0,
      last_used_at TIMESTAMP NULL,
      daily_usage_count INT NOT NULL DEFAULT 0,
      daily_usage_date DATE NULL,
      max_usage_total INT NULL,
      max_usage_per_day INT NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_theme_word_id (theme_word_id),
      CONSTRAINT fk_theme_word_variants_word FOREIGN KEY (theme_word_id) REFERENCES theme_words(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

  $sqlEvents = "CREATE TABLE IF NOT EXISTS theme_word_usage_events (
      id INT NOT NULL AUTO_INCREMENT,
      theme_word_id INT NULL,
      variant_id INT NULL,
      variant_text VARCHAR(255) NULL,
      context VARCHAR(64) NULL,
      source VARCHAR(64) NULL,
      used_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_theme_word_id (theme_word_id),
      KEY idx_variant_id (variant_id),
      KEY idx_used_at (used_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

  try {
    $db->exec($sqlWords);
  } catch (Throwable $e) {
  }
  try {
    $db->exec($sqlVariants);
  } catch (Throwable $e) {
  }
  try {
    $db->exec($sqlEvents);
  } catch (Throwable $e) {
  }

  // Ensure category_id column exists (added Jan 2026)
  try {
    $cols = $db->query("SHOW COLUMNS FROM theme_words LIKE 'category_id'")->fetchAll();
    if (empty($cols)) {
      $db->exec("ALTER TABLE theme_words ADD COLUMN category_id INT NULL AFTER category");

      // Migration: Map existing category names to IDs
      $cats = $db->query("SELECT id, name FROM theme_word_categories")->fetchAll(PDO::FETCH_ASSOC);
      foreach ($cats as $c) {
        $stmt = $db->prepare("UPDATE theme_words SET category_id = ? WHERE category = ?");
        $stmt->execute([$c['id'], $c['name']]);

        // Handle specific "Magic" -> "Magi" drift if detected
        if ($c['name'] === 'Magi') {
          $stmtM = $db->prepare("UPDATE theme_words SET category_id = ? WHERE category = 'Magic'");
          $stmtM->execute([$c['id']]);
        }
      }
    }
  } catch (Throwable $e) {
  }

  $sqlCategories = "CREATE TABLE IF NOT EXISTS theme_word_categories (
      id INT NOT NULL AUTO_INCREMENT,
      name VARCHAR(64) NOT NULL,
      slug VARCHAR(64) NOT NULL,
      sort_order INT NOT NULL DEFAULT 0,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

  try {
    $db->exec($sqlCategories);
  } catch (Throwable $e) {
  }

  wf_seed_theme_word_categories($db);
  wf_seed_theme_words($db);
}

/**
 * Seed starter categories
 */
function wf_seed_theme_word_categories(PDO $db): void
{
  try {
    $count = (int) $db->query("SELECT COUNT(*) FROM theme_word_categories")->fetchColumn();
    if ($count > 0)
      return;

    $categories = [
      ['name' => 'General', 'slug' => 'general', 'sort' => 0],
      ['name' => 'Frog', 'slug' => 'frog', 'sort' => 1],
      ['name' => 'Whimsical', 'slug' => 'whimsical', 'sort' => 2],
      ['name' => 'Nature', 'slug' => 'nature', 'sort' => 3],
      ['name' => 'Magic', 'slug' => 'magic', 'sort' => 4],
    ];

    foreach ($categories as $cat) {
      $sql = "INSERT INTO theme_word_categories (name, slug, sort_order) VALUES (?, ?, ?)";
      $stmt = $db->prepare($sql);
      $stmt->execute([$cat['name'], $cat['slug'], $cat['sort']]);
    }
  } catch (Throwable $e) {
  }
}

/**
 * Seed starter theme words if the table is empty
 */
function wf_seed_theme_words(PDO $db): void
{
  try {
    $normalizeTags = function ($raw): string {
      $raw = is_array($raw) ? implode(',', $raw) : (string) $raw;
      $parts = preg_split('/\\s*,\\s*/', strtolower(trim($raw))) ?: [];
      $parts = array_values(array_unique(array_filter(array_map('trim', $parts))));
      return implode(',', $parts);
    };
    $mergeTags = function ($existing, array $add) use ($normalizeTags): string {
      $existingNorm = $normalizeTags($existing);
      $addNorm = $normalizeTags($add);
      $parts = array_filter(array_merge(
        $existingNorm !== '' ? explode(',', $existingNorm) : [],
        $addNorm !== '' ? explode(',', $addNorm) : []
      ));
      return $normalizeTags($parts);
    };

    $seeds = [
      // Frog (punny-forward)
      ['base' => 'Ribbit', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Ribbit', 'Rrrribbit', 'Croak', 'Deep Croaker', 'Hop']],
      ['base' => 'Lilypad', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Lilypad', 'Floating Leaf', 'Green Landing', 'Pond Platform']],
      ['base' => 'Tadpole', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Tadpole', 'Pollywog', 'Baby Frog', 'Little Swimmer']],
      ['base' => 'Leap', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Leap', 'Hop', 'Spring', 'Great Bounds']],
      // Punny "twist" variants for the whimsical frog brand voice (inspired by: "Ponder-ful crafts - Hoppy by design")
      ['base' => 'Hoppy', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Hoppy', 'Hop-py', 'Hop-py vibes', 'Hoppy-go-lucky']],
      ['base' => 'Ponder', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Ponder-ful', 'Ponder-ish', 'Ponder-licious']],
      ['base' => 'Toad', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Toad', 'Toad-ally', 'Toad-ally charming', 'Toad-ally delightful', 'Bumpy Friend', 'Earth Dweller', 'Bufo']],
      ['base' => 'Frog', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Frog-tastic', 'Frog-mazing', 'Frog-ward bound']],
      ['base' => 'Ribbiting', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Ribbit-ing', 'Ribbiting', 'Ribbit-worthy']],
      ['base' => 'Tad', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Tad whimsical', 'Tad-bit magical', 'Tad-bit extra']],
      // Extra frog-forward puns so we don't run out of on-brand options before the lifetime cap.
      ['base' => 'Pond-er', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Pond-erful', 'Pond-er time', 'Pond-er and wander', 'Pond-erfully yours']],
      ['base' => 'Hopportunity', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Hopportunity', 'Hop-portunity knocks', 'A hop-portunity', 'Hop-portunity vibes']],
      ['base' => 'Tad-orable', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Tad-orable', 'Tad-orable charm', 'Tad-orable and proud']],
      ['base' => 'Froggy', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Froggy', 'Froggy fresh', 'Freshly froggy', 'Froggy flair']],
      ['base' => 'Croak', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Croak', 'Croak-tastic', 'Croak-worthy', 'Croak and giggle']],
      // Keep variants mostly word/hyphen tokens so matching/tracking remains reliable.
      ['base' => 'Ribbit-sip', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Ribbit-sip', 'Ribbit-sip repeat', 'Ribbit-sip routine', 'Ribbit-sip bliss']],
      ['base' => 'Hoptimism', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Hoptimism', 'Hop-timistic', 'Hop-timism', 'Hoptimistic glow']],
      ['base' => 'Lily Laughs', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Lily laughs', 'Lilypad laughs', 'Laughs on lilypads']],
      ['base' => 'Puddle Party', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Puddle party', 'Pond party', 'Splashy puddle party']],
      ['base' => 'Marsh-mellow', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Marsh-mellow', 'Marsh-mellow mood', 'Marsh-mellow magic']],
      ['base' => 'Tumbler Toad', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Tumbler toad', 'Toad in a tumbler', 'Toad-ally tumbler-ready']],
      ['base' => 'Ribbit Rhythm', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Ribbit rhythm', 'Ribbitin rhythm', 'Ribbit beat']],
      ['base' => 'Pond Vibes', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Pond vibes', 'Pond-side vibes', 'Pond-day vibes']],
      // Deprecated: "Hop & Glow" (symbol matching is fiddly). Use "Hop-glow" below.
      ['base' => 'Hop & Glow', 'cat' => 'Frog', 'punny' => false, 'variants' => ['Hop & glow', 'Hop-glow charm', 'Hop, glow, go']],
      ['base' => 'Hop-glow', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Hop-glow', 'Hop-glow charm', 'Hop-glow vibes']],
      ['base' => 'Amphi-buzzy', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Amphi-buzzy', 'Amphi-busy', 'Amphi-buzzy energy']],
      ['base' => 'Frog & Fancy', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Frog and fancy', 'Frog-fancy flair', 'Fancy-frog fun']],
      ['base' => 'Frog-et', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Frog-et me not', 'Frog-et the ordinary', 'Frog-et about it']],
      ['base' => 'Hopscotch', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Hop-scotch', 'Hopscotch joy', 'Hop-scotch charm']],
      ['base' => 'Croak-cuterie', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Croak-cuterie', 'Croak-cuterie vibes', 'Croak-cuterie charm']],
      ['base' => 'Pondemonium', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Pondemonium', 'Pondemonium energy', 'Pondemonium pop']],
      ['base' => 'Hop-piness', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Hop-piness', 'Hop-piness ahead', 'Hop-piness in a cup']],
      ['base' => 'Ribbit-ique', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Ribbit-ique', 'Ribbit-ique flair', 'Ribbit-ique charm']],
      ['base' => 'Toad-ally', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Toad-ally', 'Toad-ally fun', 'Toad-ally you']],
      ['base' => 'Tad-bit', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Tad-bit magical', 'Tad-bit extra', 'Tad-bit whimsical']],
      ['base' => 'Leapfrog', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Leapfrog', 'Leapfrog joy', 'Leapfrog whimsy']],
      ['base' => 'Pondside', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Pond-side', 'Pond-side daydream', 'Pond-side glow']],
      ['base' => 'Lilypad Lounge', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Lilypad lounge', 'Lilypad-lounge vibes', 'Lounge on lilypads']],
      ['base' => 'Croak-n-roll', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Croak-n-roll', 'Croak-n-roll energy', 'Croak-n-roll charm']],
      ['base' => 'Hop-to-it', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Hop-to-it', 'Hop-to-it hustle', 'Hop-to-it happy']],
      ['base' => 'Ribbit-ready', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Ribbit-ready', 'Ribbit-ready to go', 'Ribbit-ready vibes']],
      ['base' => 'Puddle-perfect', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Puddle-perfect', 'Puddle-perfect pop', 'Puddle-perfect charm']],
      ['base' => 'Swamp-sational', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Swamp-sational', 'Swamp-sational style', 'Swamp-sational charm']],
      ['base' => 'Marsh-velous', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Marsh-velous', 'Marsh-velous mood', 'Marsh-velous magic']],
      ['base' => 'Ribbit-Glow', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Ribbit-glow', 'Ribbit-glow vibes', 'Ribbit-glow charm']],
      ['base' => 'Frogshine', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Frogshine', 'Frogshine glow', 'Frogshine sparkle']],
      ['base' => 'Pond Spark', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Pond-spark', 'Pond-spark pop', 'Pond-spark charm']],
      ['base' => 'Sippin Lilypads', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Sippin lilypads', 'Sippin on lilypads', 'Lilypad sips']],
      ['base' => 'Croak-mazing', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Croak-mazing', 'Croak-mazing charm', 'Croak-mazing pop']],
      ['base' => 'Hop-penstance', 'cat' => 'Frog', 'punny' => true, 'variants' => ['Hop-penstance', 'Happy hop-penstance', 'Hop-penstance magic']],
      // Legacy/non-punny frog words below will be auto-deactivated by the seeded-non-punny pruning pass.
      ['base' => 'Bullfrog', 'cat' => 'Frog', 'variants' => ['Bullfrog', 'Bellow', 'King of the Pond', 'Jungle Jumper']],
      ['base' => 'Pond', 'cat' => 'Frog', 'variants' => ['Pond', 'Water Hole', 'Still Water', 'Frog Home']],
      ['base' => 'Swamp', 'cat' => 'Frog', 'variants' => ['Swamp', 'Marsh', 'Bog', 'Wetlands']],
      ['base' => 'Amphibious', 'cat' => 'Frog', 'variants' => ['Amphibious', 'Dual Life', 'Water & Land', 'Skin Breather']],
      ['base' => 'Green', 'cat' => 'Frog', 'variants' => ['Green', 'Emerald', 'Verdant Skin', 'Mossy Hue']],
      ['base' => 'Slippery', 'cat' => 'Frog', 'variants' => ['Slippery', 'Slick', 'Wet Skin', 'Hard to Catch']],
      ['base' => 'Webbed', 'cat' => 'Frog', 'variants' => ['Webbed', 'Paddle Feet', 'Swimmer Hands', 'Aquatic Grip']],
      ['base' => 'Night-singer', 'cat' => 'Frog', 'variants' => ['Night-singer', 'Moonlit Croaker', 'Evening Chorus', 'Dark Pond Vocals']],
      // Whimsical category: punny, twisty, brand-voice phrases that read like your tagline.
      ['base' => 'Whimsi-hop', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Whimsi-hop', 'Whimsi-hop charm', 'Whimsi-hop vibes']],
      ['base' => 'Ponder-spark', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Ponder-spark', 'Ponder-spark pop', 'Ponder-spark glow']],
      ['base' => 'Wander-ful', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Wander-ful', 'Wander-fully made', 'Wander-ful whimsy']],
      ['base' => 'Crafty', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Crafty', 'Crafty-cute', 'Crafty-kinda magic']],
      ['base' => 'Giggle-worthy', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Giggle-worthy', 'Giggle-worthy charm', 'Giggle-worthy pop']],
      ['base' => 'Doodle-dream', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Doodle-dream', 'Doodle-dream vibes', 'Doodle-dream charm']],
      ['base' => 'Fancy-free', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Fancy-free', 'Fancy-free fun', 'Fancy-free flair']],
      ['base' => 'Sprinkle-spark', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Sprinkle-spark', 'Sprinkle-spark charm', 'Sprinkle-spark pop']],
      ['base' => 'Whim-whirl', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Whim-whirl', 'Whim-whirl wonder', 'Whim-whirl vibes']],
      ['base' => 'Punny', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Punny', 'Punny by design', 'Punny little twist']],
      ['base' => 'Hoppily', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Hoppily ever after', 'Hoppily made', 'Hoppily by design']],
      ['base' => 'Delightful', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Delightful', 'Delight-ful', 'Delight-fully made']],
      ['base' => 'Wonder-ful', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Wonder-ful', 'Wonder-fully weird', 'Wonder-ful charm']],
      ['base' => 'Cozy-core', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Cozy-core', 'Cozy-core charm', 'Cozy-core vibes']],
      ['base' => 'Daydreamy', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Daydreamy', 'Daydreamy charm', 'Daydreamy glow']],
      ['base' => 'Twinkle-pop', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Twinkle-pop', 'Twinkle-pop charm', 'Twinkle-pop vibes']],
      ['base' => 'Whimsy-spritz', 'cat' => 'Whimsical', 'punny' => true, 'variants' => ['Whimsy-spritz', 'Whimsy-spritz charm', 'Whimsy-spritz vibes']],
      // Other categories: add punny options too (these categories can be activated later).
      ['base' => 'Fern-tastic', 'cat' => 'Nature', 'punny' => true, 'variants' => ['Fern-tastic', 'Fern-tastic flair', 'Fern-tastic charm']],
      ['base' => 'Leaf-it', 'cat' => 'Nature', 'punny' => true, 'variants' => ['Leaf-it be', 'Leaf-it to me', 'Leaf-it and smile']],
      ['base' => 'Bloom-boom', 'cat' => 'Nature', 'punny' => true, 'variants' => ['Bloom-boom', 'Bloom-boom pop', 'Bloom-boom vibes']],
      ['base' => 'Moss-ly', 'cat' => 'Nature', 'punny' => true, 'variants' => ['Moss-ly magical', 'Moss-ly made', 'Moss-ly charming']],
      ['base' => 'Petal-to-the-metal', 'cat' => 'Nature', 'punny' => true, 'variants' => ['Petal-to-the-metal', 'Petal-to-the-metal pop', 'Petal-to-the-metal charm']],
      ['base' => 'Branch-out', 'cat' => 'Nature', 'punny' => true, 'variants' => ['Branch-out', 'Branch-out bliss', 'Branch-out vibes']],
      ['base' => 'Spell-abrate', 'cat' => 'Magic', 'punny' => true, 'variants' => ['Spell-abrate', 'Spell-abrate sparkle', 'Spell-abrate charm']],
      ['base' => 'Rune-derful', 'cat' => 'Magic', 'punny' => true, 'variants' => ['Rune-derful', 'Rune-derful charm', 'Rune-derful vibes']],
      ['base' => 'Hex-tra', 'cat' => 'Magic', 'punny' => true, 'variants' => ['Hex-tra', 'Hex-tra sparkle', 'Hex-tra charm']],
      ['base' => 'Potion-ally', 'cat' => 'Magic', 'punny' => true, 'variants' => ['Potion-ally magical', 'Potion-ally perfect', 'Potion-ally delightful']],
      ['base' => 'Wand-erful', 'cat' => 'Magic', 'punny' => true, 'variants' => ['Wand-erful', 'Wand-erful charm', 'Wand-erful vibes']],
      ['base' => 'Pun-derful', 'cat' => 'General', 'punny' => true, 'variants' => ['Pun-derful', 'Pun-derful by design', 'Pun-derful twist']],
      ['base' => 'Quirk-tastic', 'cat' => 'General', 'punny' => true, 'variants' => ['Quirk-tastic', 'Quirk-tastic charm', 'Quirk-tastic pop']],
      ['base' => 'Craft-astic', 'cat' => 'General', 'punny' => true, 'variants' => ['Craft-astic', 'Craft-astic charm', 'Craft-astic vibes']],
      ['base' => 'Glimmer', 'cat' => 'Whimsical', 'variants' => ['Glimmer', 'Sparkle', 'Shimmer', 'Faint Glow']],
      ['base' => 'Pixie', 'cat' => 'Whimsical', 'variants' => ['Pixie', 'Sprite', 'Forest Helper', 'Tiny Wing']],
      ['base' => 'Enchanted', 'cat' => 'Whimsical', 'variants' => ['Enchanted', 'Magical', 'Spellbinding', 'Charmed']],
      ['base' => 'Dream', 'cat' => 'Whimsical', 'variants' => ['Dream', 'Slumber', 'Vision', 'Night Magic']],
      ['base' => 'Fairytale', 'cat' => 'Whimsical', 'variants' => ['Fairytale', 'Fable', 'Legend', 'Folklore']],
      ['base' => 'Wonder', 'cat' => 'Whimsical', 'variants' => ['Wonder', 'Awe', 'Amazement', 'Marvel']],
      ['base' => 'Peculiar', 'cat' => 'Whimsical', 'variants' => ['Peculiar', 'Odd', 'Strange', 'Curious']],
      ['base' => 'Gossamer', 'cat' => 'Whimsical', 'variants' => ['Gossamer', 'Filmy', 'Delicate', 'Spider Silk']],
      ['base' => 'Nymph', 'cat' => 'Whimsical', 'variants' => ['Nymph', 'Kelpie', 'Dryad', 'Spirit']],
      ['base' => 'Mischief', 'cat' => 'Whimsical', 'variants' => ['Mischief', 'Playful', 'Prank', 'Trickery']],
      ['base' => 'Whim', 'cat' => 'Whimsical', 'variants' => ['Whim', 'Fancy', 'Caprice', 'Sudden Idea']],
      ['base' => 'Fable', 'cat' => 'Whimsical', 'variants' => ['Fable', 'Apothegm', 'Moral Tale', 'Allegory']],
      ['base' => 'Serendipity', 'cat' => 'Whimsical', 'variants' => ['Serendipity', 'Happy Accident', 'Fate', 'Chance']],
      ['base' => 'Starlight', 'cat' => 'Whimsical', 'variants' => ['Starlight', 'Cosmic Dust', 'Night Spark', 'Nebula']],
      ['base' => 'Moonbeam', 'cat' => 'Whimsical', 'variants' => ['Moonbeam', 'Lunar Ray', 'Silver Glow', 'Night Light']],
      ['base' => 'Moss', 'cat' => 'Nature', 'variants' => ['Moss', 'Green Carpet', 'Velvet Ground', 'Soft Patch']],
      ['base' => 'Dewdrop', 'cat' => 'Nature', 'variants' => ['Dewdrop', 'Morning Mist', 'Clear Pearl', 'Leaf Drop']],
      ['base' => 'Willow', 'cat' => 'Nature', 'variants' => ['Willow', 'Bending Branch', 'Stream Tree', 'Whispering Leaves']],
      ['base' => 'Fern', 'cat' => 'Nature', 'variants' => ['Fern', 'Frond', 'Shade Flower', 'Ancient Leaf']],
      ['base' => 'Bramble', 'cat' => 'Nature', 'variants' => ['Bramble', 'Thicket', 'Thorny Bush', 'Prickle']],
      ['base' => 'Blossom', 'cat' => 'Nature', 'variants' => ['Blossom', 'Bloom', 'Petal', 'Flower']],
      ['base' => 'Grove', 'cat' => 'Nature', 'variants' => ['Grove', 'Glade', 'Copse', 'Small Woods']],
      ['base' => 'Brook', 'cat' => 'Nature', 'variants' => ['Brook', 'Stream', 'Rill', 'Beck']],
      ['base' => 'Petrichor', 'cat' => 'Nature', 'variants' => ['Petrichor', 'Rain Scent', 'Earth Smell', 'After Storm']],
      ['base' => 'Verdant', 'cat' => 'Nature', 'variants' => ['Verdant', 'Lush', 'Greenery', 'Bursting Life']],
      ['base' => 'Bark', 'cat' => 'Nature', 'variants' => ['Bark', 'Tree Skin', 'Rough Layer', 'Oaken Shield']],
      ['base' => 'Roots', 'cat' => 'Nature', 'variants' => ['Roots', 'Foundation', 'Earth Anchor', 'Deep Reach']],
      ['base' => 'Canopy', 'cat' => 'Nature', 'variants' => ['Canopy', 'Forest Roof', 'Leaf Ceiling', 'High Green']],
      ['base' => 'Fauna', 'cat' => 'Nature', 'variants' => ['Fauna', 'Wildlife', 'Creatures', 'Living Things']],
      ['base' => 'Flora', 'cat' => 'Nature', 'variants' => ['Flora', 'Plant Life', 'Botanicals', 'Greenery']],
      ['base' => 'Spell', 'cat' => 'Magic', 'variants' => ['Spell', 'Incantation', 'Charm', 'Woven Magic']],
      ['base' => 'Potion', 'cat' => 'Magic', 'variants' => ['Potion', 'Brew', 'Elixir', 'Concoction']],
      ['base' => 'Portals', 'cat' => 'Magic', 'variants' => ['Portal', 'Gateway', 'Mystic Gate', 'Rift']],
      ['base' => 'Arcane', 'cat' => 'Magic', 'variants' => ['Arcane', 'Ancient Magic', 'Lost Art', 'Forbidden']],
      ['base' => 'Ritual', 'cat' => 'Magic', 'variants' => ['Ritual', 'Rite', 'Ceremony', 'Custom']],
      ['base' => 'Sigil', 'cat' => 'Magic', 'variants' => ['Sigil', 'Symbol', 'Mark', 'Glyph']],
      ['base' => 'Rune', 'cat' => 'Magic', 'variants' => ['Rune', 'Letter', 'Stone Carving', 'Script']],
      ['base' => 'Ethereal', 'cat' => 'Magic', 'variants' => ['Ethereal', 'Ghostly', 'Otherworldly', 'Light']],
      ['base' => 'Alchemy', 'cat' => 'Magic', 'variants' => ['Alchemy', 'Transmutation', 'Solve et Coagula', 'Base to Gold']],
      ['base' => 'Labyrinth', 'cat' => 'Magic', 'variants' => ['Labyrinth', 'Maze', 'Intricate', 'Deep Paths']],
      ['base' => 'Celestial', 'cat' => 'Magic', 'variants' => ['Celestial', 'Heavenly', 'Stellar', 'Cosmic']],
      ['base' => 'Talisman', 'cat' => 'Magic', 'variants' => ['Talisman', 'Amulet', 'Lucky Charm', 'Warded Object']],
      ['base' => 'Artifact', 'cat' => 'Magic', 'variants' => ['Artifact', 'Ancient Object', 'Relic', 'Historical Item']],
      ['base' => 'Adventure', 'cat' => 'General', 'variants' => ['Adventure', 'Quest', 'Journey', 'Exploration']],
      ['base' => 'Mystery', 'cat' => 'General', 'variants' => ['Mystery', 'Secret', 'Enigma', 'Hidden Path']],
      ['base' => 'Legacy', 'cat' => 'General', 'variants' => ['Legacy', 'Heritage', 'Inheritance', 'Tale']],
      ['base' => 'Essence', 'cat' => 'General', 'variants' => ['Essence', 'Core', 'Spirit', 'Nature']],
      ['base' => 'Zenith', 'cat' => 'General', 'variants' => ['Zenith', 'Apex', 'Peak', 'Summit']],
      ['base' => 'Echo', 'cat' => 'General', 'variants' => ['Echo', 'Resonance', 'Reverberation', 'Memory']],
      ['base' => 'Odyssey', 'cat' => 'General', 'variants' => ['Odyssey', 'Epic', 'Long Voyage', 'Trials']],
      ['base' => 'Horizon', 'cat' => 'General', 'variants' => ['Horizon', 'Edge of World', 'Looking Forward', 'Skyline']],
      ['base' => 'Ripple', 'cat' => 'General', 'variants' => ['Ripple', 'Small Wave', 'Expansion', 'Impact']],
      ['base' => 'Quiver', 'cat' => 'General', 'variants' => ['Quiver', 'Tremble', 'Shake', 'Vibration']],
      ['base' => 'Vibrance', 'cat' => 'General', 'variants' => ['Vibrance', 'Color', 'Energy', 'Intensity']],
      ['base' => 'Luster', 'cat' => 'General', 'variants' => ['Luster', 'Sheen', 'Gloss', 'Reflection']],
    ];

    // Prepare category lookup map
    $catsMap = [];
    $allCats = $db->query("SELECT id, name FROM theme_word_categories")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allCats as $ct) {
      $catsMap[$ct['name']] = $ct['id'];
      // Special case for the "Magic" -> "Magi" experiment
      if ($ct['name'] === 'Magi')
        $catsMap['Magic'] = $ct['id'];
    }

    foreach ($seeds as $seed) {
      $catId = $catsMap[$seed['cat']] ?? ($catsMap['General'] ?? null);
      $seedTags = ['seeded'];
      if (!empty($seed['punny'])) {
        $seedTags[] = 'punny';
      }

      // Check if word exists
      $existing = $db->prepare("SELECT id FROM theme_words WHERE base_word = ?");
      $existing->execute([$seed['base']]);
      $wordId = $existing->fetchColumn();

      if (!$wordId) {
        $sql = "INSERT INTO theme_words (base_word, category, category_id, tags, is_active) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$seed['base'], $seed['cat'], $catId, $normalizeTags($seedTags), 1]);
        $wordId = $db->lastInsertId();
      } else {
        // Update existing word's category_id if missing or different
        // We also update the category name string to match the current name in categories table
        $currentCatName = array_search($catId, $catsMap);
        $rowTags = '';
        try {
          $row = $db->prepare("SELECT tags FROM theme_words WHERE id = ?");
          $row->execute([$wordId]);
          $rowTags = (string) ($row->fetchColumn() ?? '');
        } catch (Throwable $e) {
        }
        $merged = $mergeTags($rowTags, $seedTags);
        $db->prepare("UPDATE theme_words SET category_id = ?, category = ?, tags = ? WHERE id = ?")->execute([$catId, $currentCatName ?: $seed['cat'], $merged, $wordId]);
      }

      foreach ($seed['variants'] as $v) {
        // Check if variant exists for this word
        $existingV = $db->prepare("SELECT id FROM theme_word_variants WHERE theme_word_id = ? AND variant_text = ?");
        $existingV->execute([$wordId, $v]);
        if (!$existingV->fetchColumn()) {
          $sqlV = "INSERT INTO theme_word_variants (theme_word_id, variant_text, is_active) VALUES (?, ?, ?)";
          $stmtV = $db->prepare($sqlV);
          $stmtV->execute([$wordId, $v, 1]);
        }
      }
    }

    // Cleanup: disable a couple legacy entries that use symbol-heavy tokens.
    // This reduces matching/tracking edge cases and keeps the list "punny but clean".
    try {
      $db->exec("UPDATE theme_words SET is_active = 0 WHERE base_word IN ('Ribbit & Sip', 'Hop & Glow')");
      $db->exec("UPDATE theme_word_variants SET is_active = 0 WHERE variant_text LIKE '%&%'");
      $db->exec("UPDATE theme_word_variants SET is_active = 0 WHERE variant_text LIKE 'Hop, glow, go%'");

      // Main prune: deactivate seeded theme words that are not marked punny.
      // (User asked to remove non-punny theme words across categories.)
      $db->exec("UPDATE theme_words SET is_active = 0 WHERE tags LIKE '%seeded%' AND tags NOT LIKE '%punny%'");
    } catch (Throwable $e) {
    }
  } catch (Throwable $e) {
    // Fail silently in initializer
  }
}
