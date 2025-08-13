<?php
/**
 * Image Carousel Component
 *
 * A reusable carousel component for displaying multiple item images
 * that matches the WhimsicalFrog theme
 */

function renderImageCarousel($itemId, $images = [], $options = [])
{
    $defaults = [
        'id' => 'carousel-' . $itemId,
        'height' => '300px',
        'className' => '',
        'showThumbnails' => true,
        'showIndicators' => false,
        'showControls' => true,
        'autoplay' => false
    ];

    $opts = array_merge($defaults, $options);
    $carouselId = $opts['id'];

    if (empty($images)) {
        // Show elegant CSS-only fallback instead of placeholder image
        return '
        <div class="image-carousel-container carousel-container carousel_container_height ' . $opts['className'] . '">
            <div class="carousel-placeholder height_100 display_flex flex_col align_center justify_center bg_f8f9fa border_radius_normal color_6b7280">
                <div class="carousel-placeholder-icon font_size_3rem margin_bottom_10 opacity_07">📷</div>
                <div class="carousel-placeholder-text font_size_0_875 font_weight_500">No Image Available</div>
            </div>
        </div>';
    }

    $primaryImage = null;
    $otherImages = [];

    foreach ($images as $image) {
        if ($image['is_primary']) {
            $primaryImage = $image;
        } else {
            $otherImages[] = $image;
        }
    }

    // If no primary image, use first image as primary
    if (!$primaryImage && !empty($images)) {
        $primaryImage = $images[0];
        $otherImages = array_slice($images, 1);
    }

    $allImages = $primaryImage ? array_merge([$primaryImage], $otherImages) : $images;
    $imageCount = count($allImages);

    ob_start();
    ?>
    
    <div class="image-carousel-container carousel-container carousel_container_height <?= $opts['className'] ?>" id="<?= $carouselId ?>" data-autoplay="<?= $opts['autoplay'] ? 'true' : 'false' ?>">
        <!- Main Image Display ->
        <div class="carousel-main-image position_relative height_100 overflow_hidden border_radius_normal bg_f8f9fa">
            <?php foreach ($allImages as $index => $image): ?>
                <div class="carousel-slide <?= $index === 0 ? 'carousel-image-active' : 'carousel-image-inactive' ?> position_absolute top_0 left_0 width_100 height_100 transition_opacity_300" 
                     data-slide="<?= $index ?>">
                    <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                         alt="<?= htmlspecialchars($image['alt_text'] ?: 'Item image') ?>"
                         class="carousel-img width_100 height_100 object_fit_contain bg_white"
                         data-fallback="placeholder">
                    <?php if ($image['is_primary'] && isset($GLOBALS['isAdmin']) && $GLOBALS['isAdmin'] && isset($_GET['page']) && strpos($_GET['page'], 'admin') === 0): ?>
                        <div class="carousel-primary-badge position_absolute top_10 right_10 bg_brand_primary color_white padding_5_10 border_radius_small font_size_12 font_weight_bold">
                            Primary
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if ($opts['showControls'] && $imageCount > 1): ?>
                <!- Navigation Controls ->
                <button data-action="changeSlide" data-params='{"carouselId":"<?= $carouselId ?>","direction":-1}' class="btn btn-icon-only carousel__nav-btn carousel__nav-btn-prev">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <button data-action="changeSlide" data-params='{"carouselId":"<?= $carouselId ?>","direction":1}' class="btn btn-icon-only carousel__nav-btn carousel__nav-btn-next">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($opts['showThumbnails'] && $imageCount > 1): ?>
            <!- Thumbnail Navigation ->
            <div class="carousel-thumbnails display_flex gap_8 margin_top_10 justify_center flex_wrap">
                <?php foreach ($allImages as $index => $image): ?>
                    <div class="carousel-thumbnail <?= $index === 0 ? 'carousel-thumbnail-active carousel_border_color_active' : 'carousel_border_color_inactive' ?> width_60 height_60 border_2_solid overflow_hidden cursor_pointer transition_border_300 position_relative border_radius_small" 
                         data-slide="<?= $index ?>"
                         data-action="goToSlide"
                         data-params='{"carouselId":"<?= $carouselId ?>","index":<?= $index ?>}'>
                        <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                             alt="Thumbnail <?= $index + 1 ?>"
                             class="carousel-thumbnail-img width_100 height_100 object_fit_cover"
                             data-fallback="thumbnail">
                        <?php if ($image['is_primary'] && isset($GLOBALS['isAdmin']) && $GLOBALS['isAdmin'] && isset($_GET['page']) && strpos($_GET['page'], 'admin') === 0): ?>
                            <div class="position_absolute color_white border_radius_full display_flex align_center justify_center bg_brand_primary top_2 right_2 width_16 height_16 font_size_10">
                                ⭐
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($opts['showIndicators'] && $imageCount > 1 && !$opts['showThumbnails']): ?>
            <!- Dot Indicators ->
            <div class="carousel-indicators display_flex gap_8 margin_top_10 justify_center">
                <?php for ($i = 0; $i < $imageCount; $i++): ?>
                    <button data-action="goToSlide" data-params='{"carouselId":"<?= $carouselId ?>","index":<?= $i ?>}' class="carousel-indicator <?= $i === 0 ? 'active carousel_indicator_bg_active' : 'carousel_indicator_bg_inactive' ?> border_radius_full border_none cursor_pointer transition_all_300 width_12 height_12" 
                            data-slide="<?= $i ?>">
                    </button>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Carousel JS moved to Vite module: src/modules/image-carousel.js -->
    
    <?php
    return ob_get_clean();
}

function displayImageCarousel($sku, $showPrimaryBadge = false, $extraClasses = '')
{
    global $pdo;

    try {
        // Get item images for this SKU
        $stmt = $pdo->prepare("SELECT * FROM item_images WHERE sku = ? ORDER BY is_primary DESC, id ASC");
        $stmt->execute([$sku]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($images)) {
            // No images found, show elegant CSS-only fallback
            echo '<div class="width_100 product_image_placeholder display_flex flex_col align_center justify_center text_align_center bg_f8f9fa border_radius_normal color_6b7280 ' . $extraClasses . '">';
            echo '<div class="product_image_placeholder_icon font_size_3rem margin_bottom_10 opacity_07">📷</div>';
            echo '<div class="product_image_placeholder_text font_size_0_9 font_weight_500">No Image Available</div>';
            echo '</div>';
            return;
        }

        // ... existing code ...
    } catch (PDOException $e) {
        // Handle database connection error
        echo "Database connection error: " . $e->getMessage();
    }
}


?> 