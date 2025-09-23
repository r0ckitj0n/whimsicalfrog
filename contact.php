<?php
// Contact Us page - content loaded from business settings and handled via API
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
require_once __DIR__ . '/api/business_settings_helper.php';

// Ensure we read fresh settings (avoid cross-request static cache in FPM)
if (class_exists('BusinessSettings')) {
    BusinessSettings::clearCache();
}

// Generate or fetch CSRF for contact form
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['contact_csrf'];

$title = BusinessSettings::get('contact_page_title', 'Contact Us');
$intro = BusinessSettings::get('contact_page_intro', "<p>Have a question or special request?<br>Send us a message and we'll get back to you soon.</p>");
$businessEmail = BusinessSettings::getBusinessEmail();
$businessPhone = BusinessSettings::get('business_phone', '');
$businessAddress = BusinessSettings::get('business_address', '');
// Owner name (if available)
$businessOwner = BusinessSettings::get('business_owner', '');
// Extras for modal details
$businessName = BusinessSettings::getBusinessName();
$businessSiteUrl = BusinessSettings::getSiteUrl('');
// Optional: business hours
$businessHours = BusinessSettings::get('business_hours', '');
?>

<div class="page-content container mx-auto px-4 py-8">
  <?php if (!empty($businessEmail) || !empty($businessPhone) || !empty($businessAddress)): ?>
  <div class="wf-reveal-company-wrap">
    <button
      type="button"
      id="wf-reveal-company-btn"
      class="wf-reveal-company-btn"
      data-enc-email="<?php echo htmlspecialchars(base64_encode($businessEmail)); ?>"
      data-enc-phone="<?php echo htmlspecialchars(base64_encode($businessPhone)); ?>"
      data-enc-address="<?php echo htmlspecialchars(base64_encode($businessAddress)); ?>"
      data-enc-owner="<?php echo htmlspecialchars(base64_encode($businessOwner)); ?>"
      data-enc-name="<?php echo htmlspecialchars(base64_encode($businessName)); ?>"
      data-enc-site="<?php echo htmlspecialchars(base64_encode($businessSiteUrl)); ?>"
      data-enc-hours="<?php echo htmlspecialchars(base64_encode($businessHours)); ?>"
      aria-label="Solve a quick check to reveal our email, phone, and address in a secure modal"
      title="We use a quick human check to protect our contact details from spam bots."
    >
      Reveal Company Information
    </button>
  </div>
  <?php endif; ?>
  <div class="wf-contact-card">
  <div class="prose max-w-none">
    <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($title); ?></h1>
    <div class="content leading-relaxed text-gray-800 mb-6">
      <?php echo is_string($intro) ? $intro : ''; ?>
    </div>

    <div class="grid grid-cols-1 gap-8">
      <div>
        <form id="wf-contact-form" class="space-y-4 wf-contact-form" novalidate>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>" />
          <!-- Honeypot -->
          <input type="text" name="website" value="" autocomplete="off" class="wf-honeypot" tabindex="-1" aria-hidden="true">

          <div>
            <label for="name" class="block font-medium mb-1">Name</label>
            <input id="name" name="name" type="text" class="w-full border rounded px-3 py-2" required maxlength="100" autocomplete="name" />
          </div>
          <div>
            <label for="email" class="block font-medium mb-1">Email</label>
            <input id="email" name="email" type="email" class="w-full border rounded px-3 py-2" required maxlength="255" autocomplete="email" />
          </div>
          <div>
            <label for="subject" class="block font-medium mb-1">Subject (optional)</label>
            <input id="subject" name="subject" type="text" class="w-full border rounded px-3 py-2" maxlength="150" autocomplete="off" />
          </div>
          <div>
            <label for="message" class="block font-medium mb-1">Message</label>
            <textarea id="message" name="message" class="w-full border rounded px-3 py-2" rows="5" required maxlength="5000" autocomplete="off"></textarea>
          </div>
          <div>
            <button type="submit" class="wf-submit-btn" id="wf-contact-submit">
              Submit
            </button>
          </div>
          <p id="wf-contact-status" class="text-sm"></p>
        </form>
      </div>
      
    </div>
  </div>
 </div>
