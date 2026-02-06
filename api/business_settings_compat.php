<?php

// Global helper functions for backward compatibility
function getBusinessSetting($key, $default = null)
{
    return BusinessSettings::get($key, $default);
}

function getBusinessName()
{
    return BusinessSettings::getBusinessName();
}

function getBusinessEmail()
{
    return BusinessSettings::getBusinessEmail();
}

function getPrimaryColor()
{
    return BusinessSettings::getPrimaryColor();
}

function getPaymentMethods()
{
    return BusinessSettings::getPaymentMethods();
}

function getShippingMethods()
{
    return BusinessSettings::getShippingMethods();
}

/**
 * Get a random cart button text from the configured variations
 * @return string Random cart button text
 */
function getRandomCartButtonText()
{
    try {
        $cartTexts = getBusinessSetting('cart_button_texts', '["Add to Cart"]');

        // Parse JSON if it's a string
        if (is_string($cartTexts)) {
            $cartTexts = json_decode($cartTexts, true);
        }

        // Ensure we have an array with at least one option
        if (!is_array($cartTexts) || empty($cartTexts)) {
            return 'Add to Cart';
        }

        // Return a random cart button text
        return $cartTexts[array_rand($cartTexts)];

    } catch (Exception $e) {
        // Fallback to default if there's any error
        return 'Add to Cart';
    }
}
