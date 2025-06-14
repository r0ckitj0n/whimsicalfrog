# Product Images System - WhimsicalFrog

## 🖼️ Overview

The WhimsicalFrog website now supports multiple images per product with a standardized naming convention and carousel display system.

## 📝 Naming Convention

### All Images Use Letter Suffixes
- **Format**: `{PRODUCT_ID}{LETTER}.{extension}`
- **Letters**: A, B, C, D, E, F, G, H, I, J, K, L, M, N, O, P, Q, R, S, T, U, V, W, X, Y, Z
- **Examples**: 
  - `TS001A.png` (first/primary image)
  - `TS001B.jpg` (second image)
  - `TS001C.webp` (third image)
  - `TS001D.png` (fourth image)

### Supported Formats
- PNG (`.png`)
- JPEG (`.jpg`, `.jpeg`)
- WebP (`.webp`)
- GIF (`.gif`)

## 🗂️ File Organization

```
images/products/
├── TS001A.png          # Primary image for T-Shirt TS001
├── TS001B.jpg          # Second image for TS001
├── TS001C.webp         # Third image for TS001
├── MG001A.png          # Primary image for Mug MG001
├── MG001B.jpg          # Second image for MG001
├── TU001A.png          # Primary image for Tumbler TU001
└── placeholder.png     # Fallback image
```

## 🎠 Display System

### Single Image Products
- Display the primary image directly
- No carousel needed

### Multiple Image Products
- Automatic carousel with navigation controls
- Thumbnail navigation (optional)
- Primary image badge indicator
- Responsive design

## 🔧 Technical Implementation

### Database Structure
```sql
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(20) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    alt_text VARCHAR(255) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Key Components

1. **Multi-Image Upload**: `process_multi_image_upload.php`
2. **Image Carousel**: `components/image_carousel.php`
3. **Helper Functions**: `includes/product_image_helpers.php`
4. **API Endpoints**:
   - `api/get_product_images.php`
   - `api/set_primary_image.php`
   - `api/delete_product_image.php`

## 📤 Upload Process

### Admin Interface
1. Navigate to Admin → Inventory → Edit Product
2. Use the "Product Images" section
3. Select multiple files
4. Choose options:
   - ✅ Set as Primary Image
   - ✅ Overwrite Existing Images
5. Upload images

### Automatic Naming
- All images: `{PRODUCT_ID}A.{ext}`, `{PRODUCT_ID}B.{ext}`, `{PRODUCT_ID}C.{ext}`, etc.
- First uploaded image becomes `A`, second becomes `B`, and so on
- Overwrites existing files if "Overwrite" is checked

## 🎯 Features

### Image Management
- ✅ Multiple images per product (unlimited)
- ✅ Primary image designation
- ✅ Drag-and-drop upload interface
- ✅ Individual image deletion
- ✅ Primary image switching
- ✅ Automatic file naming
- ✅ File size validation (5MB max)
- ✅ Format validation

### Display Features
- ✅ Responsive image carousel
- ✅ Thumbnail navigation
- ✅ Navigation controls (prev/next)
- ✅ Primary image indicators
- ✅ Fallback to placeholder
- ✅ Theme-consistent styling
- ✅ Mobile-friendly design

### Integration
- ✅ Shop page carousel display
- ✅ Room page integration
- ✅ Admin inventory management
- ✅ Cart system compatibility
- ✅ Database synchronization

## 🧪 Testing

Visit `/test_multi_images.php` to:
- Test multi-image uploads
- View carousel functionality
- Test API endpoints
- See naming convention examples

## 📊 Current Products

| Product ID | Images | Primary Image |
|------------|--------|---------------|
| TS001      | 1      | TS001A.png    |
| TS002      | 1      | TS002A.webp   |
| TU001      | 1      | TU001A.png    |
| TU002      | 1      | TU002A.png    |
| AW001      | 1      | AW001A.png    |
| GN001      | 1      | GN001A.png    |
| MG001      | 1      | MG001A.png    |
| TEST001    | 3      | TEST001A.png  |

## 🔄 Migration Notes

- All existing images have been migrated to the new system
- Primary images maintain their original names
- Database tables synchronized (inventory, products, product_images)
- No manual intervention required for existing products

## 🎨 Styling

The carousel system uses the WhimsicalFrog brand colors:
- Primary Green: `#87ac3a`
- Light Green: `#a3cc4a`
- Consistent with site theme

## 🚀 Future Enhancements

Potential future features:
- Image compression/optimization
- Bulk image upload
- Image editing tools
- Advanced sorting options
- Image metadata management
- SEO optimization for images

---

*Last Updated: June 2024*
*System Version: 2.0* 