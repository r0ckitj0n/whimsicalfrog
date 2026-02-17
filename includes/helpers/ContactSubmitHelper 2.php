<?php
/**
 * includes/helpers/ContactSubmitHelper.php
 * Helper class for contact form submission emails
 */

class ContactSubmitHelper {
    public static function getAdminEmailBody($business_name, $brandPrimary, $name, $email, $cleanSubject, $message) {
        return "<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Contact Message - " . htmlspecialchars($business_name) . "</title>
  <style>
    body.email-body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
    .email-wrapper { max-width:600px; margin:0 auto; padding:16px; }
    .email-header { background: " . $brandPrimary . "; color:#fff; padding:16px; text-align:center; }
    .email-title { margin:0; font-size:20px; }
    .email-section { margin:16px 0; }
    blockquote { margin:12px 0; padding-left:12px; border-left:3px solid #eee; color:#555; }
  </style>
</head>
<body class='email-body'>
  <div class='email-header'><h1 class='email-title'>" . htmlspecialchars($business_name) . " — New Contact Message</h1></div>
  <div class='email-wrapper'>
    <div class='email-section'>
      <p><strong>From:</strong> " . htmlspecialchars($name) . " &lt;" . htmlspecialchars($email) . "&gt;</p>
      <p><strong>Subject:</strong> " . htmlspecialchars($cleanSubject) . "</p>
      <p><strong>Message:</strong></p>
      <blockquote>" . nl2br(htmlspecialchars($message)) . "</blockquote>
    </div>
  </div>
</body>
</html>";
    }

    public static function getUserAckEmailBody($business_name, $brandPrimary, $brandSecondary, $name, $message) {
        $businessEmail = BusinessSettings::getBusinessEmail();
        return "<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Thank you for contacting " . htmlspecialchars($business_name) . "</title>
  <style>
    body.email-body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
    .preheader { display:none; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden; mso-hide:all; }
    .email-wrapper { max-width:600px; margin:0 auto; padding:16px; }
    .email-header { background: " . $brandPrimary . "; color:#fff; padding:16px; text-align:center; }
    .email-title { margin:0; font-size:20px; }
    .email-section { margin:16px 0; }
    .email-footer { margin-top:24px; font-size:12px; color:#666; text-align:center; }
    blockquote { margin:12px 0; padding-left:12px; border-left:3px solid #eee; color:#555; }
    a { color: " . $brandSecondary . "; }
  </style>
</head>
<body class='email-body'>
  <div class='preheader'>Thanks for reaching out. We appreciate your interest and will be in touch shortly.</div>
  <div class='email-header'><h1 class='email-title'>" . htmlspecialchars($business_name) . "</h1></div>
  <div class='email-wrapper'>
    <div class='email-section'>
      <p>Hi " . htmlspecialchars($name) . ",</p>
      <p>Thank you for contacting <strong>" . htmlspecialchars($business_name) . "</strong>. We have received your message and will get back to you as soon as possible.</p>
      <p><strong>Your message:</strong></p>
      <blockquote>" . nl2br(htmlspecialchars($message)) . "</blockquote>
    </div>
    <div class='email-footer'>
      <p class='m-0'>Contact: <a href='mailto:" . htmlspecialchars($businessEmail) . "'>" . htmlspecialchars($businessEmail) . "</a></p>
    </div>
  </div>
</body>
</html>";
    }
}
