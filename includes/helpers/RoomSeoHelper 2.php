<?php
/**
 * includes/helpers/RoomSeoHelper.php
 * Helper for generating room SEO and structured data
 */

class RoomSeoHelper {
    public static function generateStructuredData($seoData) {
        $structuredData = [
            "@context" => "https://schema.org",
            "@type" => "CollectionPage",
            "name" => $seoData['title'],
            "description" => $seoData['description'],
            "url" => "https://whimsicalfrog.us" . $seoData['canonical'],
            "image" => "https://whimsicalfrog.us/" . $seoData['image'],
            "mainEntity" => [
                "@type" => "ItemList",
                "name" => $seoData['category'] . " Collection",
                "numberOfItems" => count($seoData['items']),
                "itemListElement" => []
            ]
        ];

        foreach ($seoData['items'] as $index => $item) {
            $structuredData['mainEntity']['itemListElement'][] = [
                "@type" => "ListItem",
                "position" => $index + 1,
                "item" => [
                    "@type" => "Product",
                    "name" => $item['item_name'] ?? $item['name'],
                    "sku" => $item['sku'],
                    "description" => $item['description'] ?? '',
                    "offers" => [
                        "@type" => "Offer",
                        "price" => $item['retail_price'] ?? $item['price'],
                        "priceCurrency" => "USD",
                        "availability" => ($item['stock_quantity'] ?? 0) > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock"
                    ]
                ]
            ];
        }

        return json_encode($structuredData, JSON_UNESCAPED_SLASHES);
    }

    public static function renderSeoTags($seoData) {
        $title = htmlspecialchars($seoData['title'] ?? '');
        $description = htmlspecialchars($seoData['description'] ?? '');
        $category = htmlspecialchars($seoData['category'] ?? '');
        $canonical = htmlspecialchars($seoData['canonical'] ?? '');
        $image = htmlspecialchars($seoData['image'] ?? '');
        $structured = self::generateStructuredData($seoData);

        return "
        <!-- SEO Meta Tags -->
        <title>{$title} | WhimsicalFrog</title>
        <meta name=\"description\" content=\"{$description}\">
        <meta name=\"keywords\" content=\"{$category}, WhimsicalFrog, custom items, online store\">
        <link rel=\"canonical\" href=\"https://whimsicalfrog.us{$canonical}\">

        <!-- Open Graph Tags -->
        <meta property=\"og:title\" content=\"{$title}\">
        <meta property=\"og:description\" content=\"{$description}\">
        <meta property=\"og:image\" content=\"https://whimsicalfrog.us/{$image}\">
        <meta property=\"og:url\" content=\"https://whimsicalfrog.us{$canonical}\">
        <meta property=\"og:type\" content=\"website\">

        <!-- Twitter Card Tags -->
        <meta name=\"twitter:card\" content=\"summary_large_image\">
        <meta name=\"twitter:title\" content=\"{$title}\">
        <meta name=\"twitter:description\" content=\"{$description}\">
        <meta name=\"twitter:image\" content=\"https://whimsicalfrog.us/{$image}\">

        <!-- Structured Data -->
        <script type=\"application/ld+json\">
        {$structured}
        </script>";
    }
}
