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
    $seeds = [
      ['base' => 'Ribbit', 'cat' => 'Frog', 'variants' => ['Ribbit', 'Rrrribbit', 'Croak', 'Deep Croaker', 'Hop']],
      ['base' => 'Lilypad', 'cat' => 'Frog', 'variants' => ['Lilypad', 'Floating Leaf', 'Green Landing', 'Pond Platform']],
      ['base' => 'Tadpole', 'cat' => 'Frog', 'variants' => ['Tadpole', 'Pollywog', 'Baby Frog', 'Little Swimmer']],
      ['base' => 'Leap', 'cat' => 'Frog', 'variants' => ['Leap', 'Hop', 'Spring', 'Great Bounds']],
      ['base' => 'Bullfrog', 'cat' => 'Frog', 'variants' => ['Bullfrog', 'Bellow', 'King of the Pond', 'Jungle Jumper']],
      ['base' => 'Pond', 'cat' => 'Frog', 'variants' => ['Pond', 'Water Hole', 'Still Water', 'Frog Home']],
      ['base' => 'Swamp', 'cat' => 'Frog', 'variants' => ['Swamp', 'Marsh', 'Bog', 'Wetlands']],
      ['base' => 'Amphibious', 'cat' => 'Frog', 'variants' => ['Amphibious', 'Dual Life', 'Water & Land', 'Skin Breather']],
      ['base' => 'Toad', 'cat' => 'Frog', 'variants' => ['Toad', 'Bumpy Friend', 'Earth Dweller', 'Bufo']],
      ['base' => 'Green', 'cat' => 'Frog', 'variants' => ['Green', 'Emerald', 'Verdant Skin', 'Mossy Hue']],
      ['base' => 'Slippery', 'cat' => 'Frog', 'variants' => ['Slippery', 'Slick', 'Wet Skin', 'Hard to Catch']],
      ['base' => 'Webbed', 'cat' => 'Frog', 'variants' => ['Webbed', 'Paddle Feet', 'Swimmer Hands', 'Aquatic Grip']],
      ['base' => 'Night-singer', 'cat' => 'Frog', 'variants' => ['Night-singer', 'Moonlit Croaker', 'Evening Chorus', 'Dark Pond Vocals']],
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

      // Check if word exists
      $existing = $db->prepare("SELECT id FROM theme_words WHERE base_word = ?");
      $existing->execute([$seed['base']]);
      $wordId = $existing->fetchColumn();

      if (!$wordId) {
        $sql = "INSERT INTO theme_words (base_word, category, category_id, is_active) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$seed['base'], $seed['cat'], $catId, 1]);
        $wordId = $db->lastInsertId();
      } else {
        // Update existing word's category_id if missing or different
        // We also update the category name string to match the current name in categories table
        $currentCatName = array_search($catId, $catsMap);
        $db->prepare("UPDATE theme_words SET category_id = ?, category = ? WHERE id = ?")->execute([$catId, $currentCatName ?: $seed['cat'], $wordId]);
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
  } catch (Throwable $e) {
    // Fail silently in initializer
  }
}
