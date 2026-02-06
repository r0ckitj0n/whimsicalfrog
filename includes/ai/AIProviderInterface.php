<?php
// includes/ai/AIProviderInterface.php

interface AIProviderInterface
{
    /**
     * Generate marketing content.
     */
    public function generateMarketing($name, $description, $category, $brandVoice, $contentTone);

    /**
     * Generate enhanced marketing content using image insights and existing data.
     */
    public function generateEnhancedMarketing($name, $description, $category, $imageInsights, $brandVoice, $contentTone, $existingMarketingData = null);

    /**
     * Generate cost suggestion.
     */
    public function generateCost($name, $description, $category);

    /**
     * Generate cost suggestion with images.
     */
    public function generateCostWithImages($name, $description, $category, $images);

    /**
     * Generate pricing suggestion.
     */
    public function generatePricing($name, $description, $category, $cost_price);

    /**
     * Generate shipping dimensions and weight.
     */
    public function generateDimensions($name, $description, $category);

    /**
     * Analyze image for specific e-commerce details.
     */
    public function analyzeItemImage($imagePath, $existingCategories = []);

    /**
     * Generate alt text for images.
     */
    public function generateAltText($images, $name, $description, $category);

    /**
     * Generate receipt message.
     */
    public function generateReceipt($prompt);

    /**
     * Generate marketing content with images.
     */
    public function generateMarketingWithImages($name, $description, $category, $images, $brandVoice, $contentTone);

    /**
     * Generate pricing suggestion with images.
     */
    public function generatePricingWithImages($name, $description, $category, $cost_price, $images);

    /**
     * Detect object boundaries in an image for cropping.
     */
    public function detectObjectBoundaries($imagePath);

    /**
     * Supports image input?
     */
    public function supportsImages(): bool;
}
