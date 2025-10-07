<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

echo "Business Info (canonical)\n";
echo "----------------------------------------\n";
$line1 = BusinessSettings::getBusinessAddressLine1();
$line2 = BusinessSettings::getBusinessAddressLine2();
$city  = BusinessSettings::getBusinessCity();
$state = BusinessSettings::getBusinessState();
$postal= BusinessSettings::getBusinessPostal();
$block = BusinessSettings::getBusinessAddressBlock();
$name  = BusinessSettings::getBusinessName();
$phone = BusinessSettings::get('business_phone','');
$website = BusinessSettings::getSiteUrl('');

printf("Name: %s\n", $name);
printf("Phone: %s\n", $phone);
printf("Website: %s\n", $website);
printf("Line1: %s\n", $line1);
printf("Line2: %s\n", $line2);
printf("City: %s\n", $city);
printf("State: %s\n", $state);
printf("Postal: %s\n", $postal);
echo "\nAddress Block:\n$block\n";

$ok = ($line1 !== '' && $city !== '' && $state !== '' && $postal !== '');
exit($ok ? 0 : 2);
