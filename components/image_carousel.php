<?php
/**
 * Image Carousel Component
 * 
 * A reusable carousel component for displaying multiple item images
 * that matches the WhimsicalFrog theme
 */

function renderImageCarousel($itemId, $images = [], $options = []) {
    // Default options
    $defaults = [
        'showThumbnails' => true,
        'autoplay' => false,
        'showControls' => true,
        'showIndicators' => true,
        'height' => '400px',
        'className' => '',
        'id' => 'carousel-' . $itemId
    ];
    
    $opts = array_merge($defaults, $options);
    $carouselId = $opts['id'];
    
    if (empty($images)) {
        // Show elegant CSS-only fallback instead of placeholder image
        return '
        <div class="image-carousel-container ' . $opts['className'] . '" style="height: ' . $opts['height'] . ';">
            <div class="carousel-placeholder" style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px; color: #6b7280;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">📷</div>
                <div style="font-size: 0.875rem; font-weight: 500;">No Image Available</div>
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
    
    <div class="image-carousel-container <?= $opts['className'] ?>" id="<?= $carouselId ?>" style="height: <?= $opts['height'] ?>;">
        <!-- Main Image Display -->
        <div class="carousel-main-image" style="position: relative; height: 100%; overflow: hidden; border-radius: 8px; background: #f8f9fa;">
            <?php foreach ($allImages as $index => $image): ?>
                <div class="carousel-slide <?= $index === 0 ? 'active' : '' ?>" 
                     data-slide="<?= $index ?>"
                     style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: <?= $index === 0 ? '1' : '0' ?>; transition: opacity 0.3s ease;">
                    <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                         alt="<?= htmlspecialchars($image['alt_text'] ?: 'Item image') ?>"
                         style="width: 100%; height: 100%; object-fit: contain; background: white;"
                         onerror="this.style.display='none'; this.parentElement.innerHTML += '<div style=\'width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#f8f9fa;color:#6c757d;border-radius:8px;\'><div style=\'font-size:3rem;margin-bottom:0.5rem;opacity:0.7;\'>📷</div><div style=\'font-size:0.9rem;font-weight:500;\'>Image Not Found</div></div>';">
                    <?php if ($image['is_primary'] && isset($GLOBALS['isAdmin']) && $GLOBALS['isAdmin'] && isset($_GET['page']) && strpos($_GET['page'], 'admin') === 0): ?>
                        <div class="primary-badge" style="position: absolute; top: 10px; right: 10px; background: #87ac3a; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                            Primary
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <?php if ($opts['showControls'] && $imageCount > 1): ?>
                <!-- Navigation Controls -->
                <button class="carousel-prev" onclick="changeSlide('<?= $carouselId ?>', -1)"
                        style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: rgba(135, 172, 58, 0.8); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: background 0.3s;">
                    &#8249;
                </button>
                <button class="carousel-next" onclick="changeSlide('<?= $carouselId ?>', 1)"
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: rgba(135, 172, 58, 0.8); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; transition: background 0.3s;">
                    &#8250;
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($opts['showThumbnails'] && $imageCount > 1): ?>
            <!-- Thumbnail Navigation -->
            <div class="carousel-thumbnails" style="display: flex; gap: 8px; margin-top: 10px; justify-content: center; flex-wrap: wrap;">
                <?php foreach ($allImages as $index => $image): ?>
                    <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>" 
                         data-slide="<?= $index ?>"
                         onclick="goToSlide('<?= $carouselId ?>', <?= $index ?>)"
                         style="width: 60px; height: 60px; border: 2px solid <?= $index === 0 ? '#87ac3a' : '#ddd' ?>; border-radius: 6px; overflow: hidden; cursor: pointer; transition: border-color 0.3s; position: relative;">
                        <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                             alt="Thumbnail <?= $index + 1 ?>"
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.style.display='none'; this.parentElement.innerHTML = '<div style=\'width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f8f9fa;color:#6c757d;font-size:1.5rem;\'>📷</div>';">
                        <?php if ($image['is_primary'] && isset($GLOBALS['isAdmin']) && $GLOBALS['isAdmin'] && isset($_GET['page']) && strpos($_GET['page'], 'admin') === 0): ?>
                            <div style="position: absolute; top: 2px; right: 2px; background: #87ac3a; color: white; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-size: 10px;">
                                ⭐
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($opts['showIndicators'] && $imageCount > 1 && !$opts['showThumbnails']): ?>
            <!-- Dot Indicators -->
            <div class="carousel-indicators" style="display: flex; gap: 8px; margin-top: 10px; justify-content: center;">
                <?php for ($i = 0; $i < $imageCount; $i++): ?>
                    <button class="indicator <?= $i === 0 ? 'active' : '' ?>" 
                            data-slide="<?= $i ?>"
                            onclick="goToSlide('<?= $carouselId ?>', <?= $i ?>)"
                            style="width: 12px; height: 12px; border-radius: 50%; border: none; background: <?= $i === 0 ? '#87ac3a' : '#ddd' ?>; cursor: pointer; transition: background 0.3s;">
                    </button>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .carousel-prev:hover, .carousel-next:hover {
        background: rgba(135, 172, 58, 1) !important;
    }
    
    .thumbnail:hover {
        border-color: #87ac3a !important;
    }
    
    .indicator.active {
        background: #87ac3a !important;
    }
    
    .indicator:hover {
        background: #a3cc4a !important;
    }
    </style>
    
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
        
        // Update slides
        const slides = carousel.querySelectorAll('.carousel-slide');
        slides.forEach((slide, index) => {
            slide.style.opacity = index === slideIndex ? '1' : '0';
        });
        
        // Update thumbnails
        const thumbnails = carousel.querySelectorAll('.thumbnail');
        thumbnails.forEach((thumb, index) => {
            thumb.style.borderColor = index === slideIndex ? '#87ac3a' : '#ddd';
            thumb.classList.toggle('active', index === slideIndex);
        });
        
        // Update indicators
        const indicators = carousel.querySelectorAll('.indicator');
        indicators.forEach((indicator, index) => {
            indicator.style.background = index === slideIndex ? '#87ac3a' : '#ddd';
            indicator.classList.toggle('active', index === slideIndex);
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
            echo '<div class="w-full h-48 bg-gray-100 rounded-lg flex flex-col items-center justify-center text-gray-500 ' . $extraClasses . '">';
            echo '<div class="text-5xl mb-2">📷</div>';
            echo '<div class="text-sm font-medium">No Image Available</div>';
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