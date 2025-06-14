<?php
/**
 * Multi-Image System Test Page
 * 
 * This page demonstrates the new multi-image upload system and carousel functionality
 */

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/components/image_carousel.php';
require_once __DIR__ . '/includes/product_image_helpers.php';

// Get all products with images for testing
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $stmt = $pdo->query("
        SELECT DISTINCT product_id, 
               (SELECT COUNT(*) FROM product_images pi2 WHERE pi2.product_id = pi.product_id) as image_count
        FROM product_images pi 
        ORDER BY product_id
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $products = [];
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Image System Test - WhimsicalFrog</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .header {
            background: #87ac3a;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .test-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .product-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #f9f9f9;
        }
        
        .upload-form {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            border: 2px dashed #87ac3a;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .btn {
            background: #87ac3a;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #a3cc4a;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .checkbox-group {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
            margin-bottom: 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 5px;
        }
        
        .status-message {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .image-info {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🖼️ Multi-Image System Test</h1>
        <p>Testing the new product image upload system with carousel support</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="status-message status-error">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Upload Test Section -->
    <div class="test-section">
        <h2>📤 Upload Test</h2>
        <p>Test uploading multiple images for a product. Images will be named after the Product ID with letter suffixes (e.g., TS001A.jpg, TS001B.jpg, TS001C.jpg).</p>
        
        <div class="upload-form">
            <form id="multiImageUploadForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="testProductId">Product ID:</label>
                    <select id="testProductId" name="productId" required>
                        <option value="">Select a product...</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= htmlspecialchars($product['product_id']) ?>">
                                <?= htmlspecialchars($product['product_id']) ?> 
                                (<?= $product['image_count'] ?> image<?= $product['image_count'] != 1 ? 's' : '' ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="TEST001">TEST001 (New Product)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="testImages">Select Images:</label>
                    <input type="file" id="testImages" name="images[]" multiple accept="image/*" required>
                </div>
                
                <div class="form-group">
                    <label for="testAltText">Alt Text (optional):</label>
                    <input type="text" id="testAltText" name="altText" placeholder="Description for the images">
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" id="testSetPrimary" name="isPrimary"> Set as Primary Image
                        </label>
                        <label>
                            <input type="checkbox" id="testOverwrite" name="overwrite"> Overwrite Existing Images
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn">Upload Images</button>
                <button type="button" class="btn btn-secondary" onclick="refreshProductList()">Refresh Product List</button>
            </form>
            
            <div id="uploadStatus"></div>
        </div>
    </div>

    <!-- Current Images Display -->
    <div class="test-section">
        <h2>🎠 Carousel Test</h2>
        <p>View current product images with carousel functionality.</p>
        
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <h3><?= htmlspecialchars($product['product_id']) ?></h3>
                    <div class="image-info">
                        <?= $product['image_count'] ?> image<?= $product['image_count'] != 1 ? 's' : '' ?>
                    </div>
                    
                    <?php
                    // Display the product images using the carousel
                    echo renderProductImageDisplay($product['product_id'], [
                        'height' => '250px',
                        'showThumbnails' => $product['image_count'] > 1,
                        'showControls' => true,
                        'autoplay' => false,
                        'className' => 'test-carousel'
                    ]);
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- API Test Section -->
    <div class="test-section">
        <h2>🔧 API Test</h2>
        <p>Test the image management APIs.</p>
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn" onclick="testGetImages()">Test Get Images API</button>
            <button class="btn" onclick="testSetPrimary()">Test Set Primary API</button>
            <button class="btn btn-secondary" onclick="clearApiResults()">Clear Results</button>
        </div>
        
        <div id="apiResults" style="margin-top: 15px;"></div>
    </div>

    <script>
        // Upload form handling
        document.getElementById('multiImageUploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const productId = document.getElementById('testProductId').value;
            const images = document.getElementById('testImages').files;
            const altText = document.getElementById('testAltText').value;
            const isPrimary = document.getElementById('testSetPrimary').checked;
            const overwrite = document.getElementById('testOverwrite').checked;
            
            if (!productId) {
                showStatus('Please select a product ID', 'error');
                return;
            }
            
            if (images.length === 0) {
                showStatus('Please select at least one image', 'error');
                return;
            }
            
            // Add files to form data
            for (let i = 0; i < images.length; i++) {
                formData.append('images[]', images[i]);
            }
            
            formData.append('productId', productId);
            formData.append('altText', altText);
            formData.append('isPrimary', isPrimary);
            formData.append('overwrite', overwrite);
            
            showStatus('Uploading images...', 'info');
            
            fetch('/process_multi_image_upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatus(data.message, 'success');
                    
                    if (data.warnings && data.warnings.length > 0) {
                        data.warnings.forEach(warning => {
                            showStatus('Warning: ' + warning, 'error');
                        });
                    }
                    
                    // Reset form
                    document.getElementById('multiImageUploadForm').reset();
                    
                    // Refresh page after 2 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showStatus('Upload failed: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                showStatus('Upload failed: Network error', 'error');
            });
        });
        
        function showStatus(message, type) {
            const statusDiv = document.getElementById('uploadStatus');
            const className = type === 'success' ? 'status-success' : 
                             type === 'error' ? 'status-error' : 
                             'status-message';
            
            statusDiv.innerHTML = `<div class="status-message ${className}">${message}</div>`;
        }
        
        function refreshProductList() {
            window.location.reload();
        }
        
        // API Testing Functions
        function testGetImages() {
            const productId = document.getElementById('testProductId').value;
            if (!productId) {
                showApiResult('Please select a product ID first', 'error');
                return;
            }
            
            fetch(`/api/get_product_images.php?productId=${encodeURIComponent(productId)}`)
            .then(response => response.json())
            .then(data => {
                showApiResult('Get Images API Result: ' + JSON.stringify(data, null, 2), 'success');
            })
            .catch(error => {
                showApiResult('API Error: ' + error.message, 'error');
            });
        }
        
        function testSetPrimary() {
            const productId = document.getElementById('testProductId').value;
            if (!productId) {
                showApiResult('Please select a product ID first', 'error');
                return;
            }
            
            // First get images to find one to set as primary
            fetch(`/api/get_product_images.php?productId=${encodeURIComponent(productId)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.images.length > 0) {
                    const imageId = data.images[0].id;
                    
                    return fetch('/api/set_primary_image.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            productId: productId,
                            imageId: imageId
                        })
                    });
                } else {
                    throw new Error('No images found for this product');
                }
            })
            .then(response => response.json())
            .then(data => {
                showApiResult('Set Primary API Result: ' + JSON.stringify(data, null, 2), 'success');
            })
            .catch(error => {
                showApiResult('API Error: ' + error.message, 'error');
            });
        }
        
        function showApiResult(message, type) {
            const resultsDiv = document.getElementById('apiResults');
            const className = type === 'success' ? 'status-success' : 'status-error';
            
            resultsDiv.innerHTML += `<div class="status-message ${className}"><pre>${message}</pre></div>`;
        }
        
        function clearApiResults() {
            document.getElementById('apiResults').innerHTML = '';
        }
    </script>
</body>
</html> 