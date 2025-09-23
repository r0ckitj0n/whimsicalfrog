<?php
// About Us page - content loaded from business settings
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once __DIR__ . '/api/business_settings_helper.php';

$title = BusinessSettings::get('about_page_title', 'Our Story');
$content = BusinessSettings::get('about_page_content', '');

// Whimsical default story (used only if setting is blank)
$defaultStory = '<p>Once upon a time in a cozy little workshop, Calvin &amp; Lisa Lemley began crafting whimsical treasures for friends and family. What started as a weekend habit of chasing ideas and laughter soon grew into WhimsicalFrog&mdash;a tiny brand with a big heart.</p><p>Every piece we make is a small celebration of play and everyday magic: things that delight kids, spark curiosity, and make grown‑ups smile. We believe in craftsmanship, kindness, and creating goods that feel like they were made just for you.</p><p>Thank you for visiting our little corner of the pond. We hope our creations bring a splash of joy to your day!</p>';

// Fallback if DB value exists but is empty
if (!is_string($content) || trim(strip_tags($content)) === '') {
    $content = $defaultStory;
}
if (!is_string($title) || trim($title) === '') {
    $title = 'Our Story';
}
?>

<div class="page-content container mx-auto px-4 py-4">
  <div class="prose max-w-none">
    <div class="wf-cloud-card">
      <div class="content leading-relaxed text-gray-800">
        <h1 class="wf-cloud-title"><?php echo htmlspecialchars($title); ?></h1>
        <?php
          // Allow stored HTML content; ensure it's a string
          echo is_string($content) ? $content : '';
?>
      </div>
    </div>
  </div>
</div>
