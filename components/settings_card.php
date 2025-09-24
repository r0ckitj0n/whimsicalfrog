<?php
// components/settings_card.php
// Reusable renderer for settings cards (title, description, content) with theme class

if (!function_exists('wf_render_settings_card')) {
    /**
     * Render a standardized settings card.
     *
     * @param string $themeClass e.g., 'card-theme-blue'
     * @param string $title
     * @param string $description
     * @param string $contentHtml Raw inner HTML for the section-content area
     * @return string
     */
    function wf_render_settings_card(string $themeClass, string $title, string $description, string $contentHtml): string
    {
        // Escape title/description while preserving provided HTML for content
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeDesc = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

        // Standard card markup
        return <<<HTML
<section class="settings-section {$themeClass}">
  <header class="section-header">
    <h3 class="section-title">{$safeTitle}</h3>
    <p class="section-description">{$safeDesc}</p>
  </header>
  <div class="section-content">
    {$contentHtml}
  </div>
</section>
HTML;
    }
}
