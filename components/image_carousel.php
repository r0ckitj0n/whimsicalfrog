<?php
/**
 * Image Carousel Component
 * 
 * A reusable carousel component for displaying multiple item images
 * that matches the WhimsicalFrog theme
 */

function renderImageCarousel($itemId, $images = [], $options = []) {
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
    
    <div class="image-carousel-container carousel-container carousel_container_height <?= $opts['className'] ?>" id="<?= $carouselId ?>">
        <!- Main Image Display ->
        <div class="carousel-main-image position_relative height_100 overflow_hidden border_radius_normal bg_f8f9fa">
            <?php foreach ($allImages as $index => $image): ?>
                <div class="carousel-slide <?= $index === 0 ? 'carousel-image-active' : 'carousel-image-inactive' ?>" 
                     data-slide="<?= $index ?>"
                     class="position_absolute top_0 left_0 width_100 height_100 transition_opacity_300">
                    <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                         alt="<?= htmlspecialchars($image['alt_text'] ?: 'Item image') ?>"
                         class="carousel-img width_100 height_100 object_fit_contain bg_white"
                         onerror="this.style.display='none'; this.parentElement.innerHTML += '<div class=\'width_100 height_100 display_flex flex_col align_center justify_center bg_f8f9fa color_6b7280 border_radius_normal\'><div class=\'font_size_3rem margin_bottom_10 opacity_07\'>📷</div><div class=\'font_size_0_9 font_weight_500\'>Image Not Found</div></div>';">
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
                    <div class="carousel-thumbnail <?= $index === 0 ? 'carousel-thumbnail-active carousel_border_color_active' : 'carousel_border_color_inactive' ?>" 
                         data-slide="<?= $index ?>"
                         data-action="goToSlide"
                         data-params='{"carouselId":"<?= $carouselId ?>","index":<?= $index ?>}'
                         class="width_60 height_60 border_2_solid overflow_hidden cursor_pointer transition_border_300 position_relative border_radius_small">
                        <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                             alt="Thumbnail <?= $index + 1 ?>"
                             class="carousel-thumbnail-img width_100 height_100 object_fit_cover"
                             onerror="this.style.display='none'; this.parentElement.innerHTML = '<div class=\'width_100 height_100 display_flex align_center justify_center bg_f8f9fa color_6b7280 font_size_1_5rem\'>📷</div>';">
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
                    <button data-action="goToSlide" data-params='{"carouselId":"<?= $carouselId ?>","index":<?= $i ?>}' class="carousel-indicator <?= $i === 0 ? 'active carousel_indicator_bg_active' : 'carousel_indicator_bg_inactive' ?>" 
                            data-slide="<?= $i ?>"
                            class="border_radius_full border_none cursor_pointer transition_all_300 width_12 height_12">
                    </button>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Carousel functionality
    window.carouselStates = window.carouselStates || {};
    window.carouselStates['<?= $carouselId ?>'] = {
        currentSlide: 0,
        totalSlides: <?= $imageCount ?>,
        autoplay: <?= $opts['autoplay'] ? 'true' : 'false' ?>,
        autoplayInterval: null
    };
    
    function changeSlide(carouselId, direction) {
        const state = window.carouselStates[carouselId];
        const newSlide = (state.currentSlide + direction + state.totalSlides) % state.totalSlides;
        goToSlide(carouselId, newSlide);
    }
    
    function goToSlide(carouselId, slideIndex) {
        const state = window.carouselStates[carouselId];
        const carousel = document.getElementById(carouselId);
        
        // Update slides using CSS classes instead of style manipulation
        const slides = carousel.querySelectorAll('.carousel-slide');
        slides.forEach((slide, index) => {
            slide.classList.toggle('carousel-image-active', index === slideIndex);
            slide.classList.toggle('carousel-image-inactive', index !== slideIndex);
        });
        
        // Update thumbnails using CSS classes
        const thumbnails = carousel.querySelectorAll('.carousel-thumbnail');
        thumbnails.forEach((thumb, index) => {
            thumb.classList.toggle('carousel-thumbnail-active', index === slideIndex);
            thumb.classList.toggle('carousel_border_color_active', index === slideIndex);
            thumb.classList.toggle('carousel_border_color_inactive', index !== slideIndex);
        });
        
        // Update indicators using CSS classes
        const indicators = carousel.querySelectorAll('.carousel-indicator');
        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === slideIndex);
            indicator.classList.toggle('carousel_indicator_bg_active', index === slideIndex);
            indicator.classList.toggle('carousel_indicator_bg_inactive', index !== slideIndex);
        });
        
        state.currentSlide = slideIndex;
    }
    
    <?php if ($opts['autoplay'] && $imageCount > 1): ?>
    // Start autoplay
    window.carouselStates['<?= $carouselId ?>'].autoplayInterval = setInterval(() => {
        changeSlide('<?= $carouselId ?>', 1);
    }, 3000);
    
    // Pause autoplay on hover
    document.getElementById('<?= $carouselId ?>').addEventListener('mouseenter', () => {
        clearInterval(window.carouselStates['<?= $carouselId ?>'].autoplayInterval);
    });
    
    document.getElementById('<?= $carouselId ?>').addEventListener('mouseleave', () => {
        window.carouselStates['<?= $carouselId ?>'].autoplayInterval = setInterval(() => {
            changeSlide('<?= $carouselId ?>', 1);
        }, 3000);
    });
    <?php endif; ?>
    </script>
    
    <?php
    return ob_get_clean();
}

function displayImageCarousel($sku, $showPrimaryBadge = false, $extraClasses = '') {
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