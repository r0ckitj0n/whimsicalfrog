> Note: Technical Reference — Historical/Deep Technical context. For current behavior and routes, see documentation/ADMIN_GUIDE.md.

# WhimsicalFrog Website Customization Guide

**Version**: v2024.3.0 (Post-Cleanup)  
**Last Updated**: June 30, 2025  
**Features**: 150 CSS Rules, Website Logs, Enhanced Admin Panel  

## Overview

The WhimsicalFrog website is designed to be a **completely reusable platform** that can be customized for any business. You can copy this codebase, modify the database and CSS settings, and have a completely different website with its own branding, content, and functionality.

**Recent Major Updates**:
- ✅ **CSS System Streamlined** - Reduced from 691 to 150 rules across 19 categories
- ✅ **Website Logs System** - Comprehensive logging and monitoring interface
- ✅ **Enhanced Admin Panel** - Improved organization and new management tools

## Quick Start for New Business

### 1. Copy the Codebase
```bash
git clone https://github.com/r0ckitj0n/whimsicalfrog.git your-business-name
cd your-business-name
```

### 2. Setup Database
1. Create a new MySQL database for your business
2. Update `api/config.php` with your database credentials
3. Run the initialization scripts:
   ```bash
   curl "http://your-domain.com/api/init_business_settings_db.php"
   curl "http://your-domain.com/api/init_global_css_db.php"
   ```

### 3. Customize Through Admin Panel
1. Navigate to `/admin` (use default credentials: admin/admin123)
2. Go to **Website Configuration** → **General Configuration**
3. Update all business settings to match your brand

## Customization System Architecture

### Database-Driven Configuration

All website settings are stored in the `business_settings` table with these categories:

- **🎨 Branding**: Colors, logos, site name, tagline
- **🏢 Business Info**: Name, address, contact details, social media
- **🏠 Rooms**: Room system configuration and category names
- **🛒 E-commerce**: Currency, tax rates, shipping settings
- **📧 Email**: Email configuration and notifications
- **💳 Payment**: Payment methods and gateway settings
- **📦 Shipping**: Shipping methods and fees
- **🌐 Site Features**: User accounts, search, AI features
- **🔍 SEO**: Meta tags, analytics tracking
- **💰 Tax**: Tax calculation settings
- **📊 Inventory**: Stock thresholds, SKU generation
- **📋 Orders**: Order management settings
- **⚙️ Admin**: Admin interface preferences
- **🚀 Performance**: Caching and optimization

### CSS Variables System

All styling is controlled through CSS variables stored in the `global_css_rules` table:

- **Colors**: Primary, secondary, accent colors
- **Typography**: Fonts, sizes, weights
- **Layout**: Spacing, borders, shadows
- **Popups**: All popup styling is centralized
- **Room Headers**: Title and description styling
- **Buttons**: Button colors and hover effects

## Major Customization Areas

### 1. Business Branding

**Location**: Admin → Website Configuration → General Configuration → Branding

Key settings to change:
- `site_name`: Your business name
- `site_tagline`: Your business tagline
- `site_logo_url`: Path to your logo image
- `brand_primary_color`: Your primary brand color
- `brand_secondary_color`: Your secondary brand color
- `business_name`: Legal business name
- `business_description`: Business description

### 2. Room System Customization

**Location**: Admin → Website Configuration → General Configuration → Rooms

The "room" system can represent any categorization:
- **Retail Store**: Different departments (Electronics, Clothing, etc.)
- **Restaurant**: Menu categories (Appetizers, Mains, Desserts)
- **Service Business**: Service types (Consulting, Development, Support)
- **Portfolio Site**: Project categories (Web Design, Branding, Photography)

Customize these settings:
- `room_system_enabled`: Enable/disable room navigation
- `room_main_title`: Main room page title
- `room_main_description`: Description text
- `room_2_category` through `room_6_category`: Category names for each room

### 3. Product/Service Management

**Location**: Admin → Inventory

- Add your products/services through the inventory system
- Use categories that match your room configuration
- Upload images and set pricing
- Configure stock levels if applicable

### 4. Visual Styling

**Location**: Admin → Global CSS Rules

The CSS system has been streamlined to **150 essential rules** across **19 categories**:
- **Brand Colors** (6) - Primary colors and brand theme
- **Typography** (14) - Fonts, sizes, and text styling  
- **Buttons** (10) - Button variants and interactions
- **Forms** (8) - Input fields and form controls
- **Layout** (12) - Spacing, containers, and grids
- **Cards** (9) - Product cards and content layouts
- **Navigation** (7) - Header and menu styling
- **Modals** (8) - Popup styling and overlays
- **Admin Interface** (12) - Admin panel specific styling
- **Plus 10 more categories** for comprehensive customization

### 5. E-commerce Configuration

**Location**: Admin → Website Configuration → General Configuration → E-commerce

- `currency_symbol` and `currency_code`: Your local currency
- `tax_rate`: Your local tax rate
- `shipping_enabled`: Enable/disable shipping
- `payment_methods`: Available payment options
- `min_order_amount`: Minimum order requirement

## Advanced Customization

### Custom Room Backgrounds

1. Replace room background images in `/images/` directory:
   - `room2.webp` - Room 2 background
   - `room3.webp` - Room 3 background  
   - `room4.webp` - Room 4 background
   - `room5.webp` - Room 5 background
   - `room6.webp` - Room 6 background
   - `main_room.webp` - Main room background

2. Update aspect ratios in room CSS if needed

### Custom CSS Styling

1. **Global CSS Rules**: Use Admin → Global CSS Rules for database-driven styling
2. **Custom CSS Files**: Modify files in `/css/` directory:
   - `room-headers.css` - Room title and description styling
   - `room-popups.css` - Product popup styling
   - Add custom CSS files as needed

### Email Templates

**Location**: Admin → Email Configuration

Customize email templates for:
- Order confirmations
- Welcome emails
- Shipping notifications
- Admin notifications

### Website Logs Management

**Location**: Admin → Settings → Website Logs

Monitor and troubleshoot your website with comprehensive logging:
- **File-based logs** (6 types) - System monitoring, errors, server activity
- **Database logs** (7 types) - User activity, orders, inventory, admin actions
- **Search functionality** - Find specific events across all logs
- **Download and clear** - Manage log files for maintenance
- **Real-time monitoring** - Track system health and performance

### AI Features

**Location**: Admin → AI Settings

Configure AI-powered features:
- Automatic product descriptions
- Image processing and optimization
- Smart pricing suggestions
- Content generation

## Business-Specific Examples

### Example 1: Restaurant Website

**Room Configuration**:
- Room 2: "Appetizers"
- Room 3: "Main Courses"  
- Room 4: "Desserts"
- Room 5: "Beverages"
- Room 6: "Catering"

**Branding**:
- Primary Color: Warm red (#C53030)
- Secondary Color: Golden yellow (#D69E2E)
- Site Name: "Bella Vista Restaurant"
- Tagline: "Authentic Italian Cuisine"

### Example 2: Consulting Firm

**Room Configuration**:
- Room 2: "Strategy Consulting"
- Room 3: "Digital Transformation"
- Room 4: "Process Optimization"
- Room 5: "Training Programs"
- Room 6: "Support Services"

**Branding**:
- Primary Color: Professional blue (#2B6CB0)
- Secondary Color: Trustworthy gray (#4A5568)
- Site Name: "ProConsult Solutions"
- Tagline: "Driving Business Excellence"

### Example 3: Creative Agency

**Room Configuration**:
- Room 2: "Web Design"
- Room 3: "Branding"
- Room 4: "Photography"
- Room 5: "Marketing"
- Room 6: "Print Design"

**Branding**:
- Primary Color: Creative purple (#805AD5)
- Secondary Color: Vibrant orange (#DD6B20)
- Site Name: "Pixel Perfect Studio"
- Tagline: "Where Ideas Come to Life"

## File Structure for Customization

```
your-business/
├── api/
│   ├── business_settings.php      # Business settings API
│   ├── global_css_rules.php       # CSS variables API
│   └── config.php                 # Database configuration
├── css/
│   ├── room-headers.css           # Room styling
│   ├── room-popups.css            # Popup styling
│   └── [custom-styles].css       # Your custom CSS
├── images/
│   ├── room2.webp                 # Room backgrounds
│   ├── room3.webp
│   ├── [your-logo].webp          # Your branding images
│   └── [product-images]/         # Your product images
├── sections/
│   ├── admin_settings.php         # Admin interface
│   ├── room2.php                  # Room pages
│   └── room3.php
└── CUSTOMIZATION_GUIDE.md         # This guide
```

## Best Practices

### 1. Database Backup
Always backup your database before making major changes:
```bash
mysqldump -u username -p database_name > backup.sql
```

### 2. Testing Environment
Set up a staging environment to test changes before going live.

### 3. Image Optimization
- Use WebP format for better performance
- Optimize images before uploading
- Maintain consistent aspect ratios

### 4. Mobile Responsiveness
- Test all customizations on mobile devices
- Use the responsive design features built into the system

### 5. SEO Configuration
- Update meta titles and descriptions
- Configure Google Analytics tracking
- Set up proper social media meta tags

## Support and Maintenance

### Regular Updates
1. **Content**: Keep product information current
2. **Images**: Update seasonal or promotional images
3. **Settings**: Review and adjust business settings as needed
4. **Backup**: Regular database and file backups

### Performance Monitoring
- Monitor page load times
- Check image optimization
- Review database performance
- Monitor server resources

### Security
- Keep admin credentials secure
- Regular security updates
- Monitor for suspicious activity
- Use HTTPS for all transactions

## Troubleshooting

### Common Issues

1. **CSS Not Loading**: Check file permissions and paths
2. **Database Errors**: Verify connection settings in config.php
3. **Images Not Displaying**: Check image paths and permissions
4. **Admin Access**: Reset admin credentials if needed

### Getting Help

1. Check the admin logs for error messages
2. Review browser console for JavaScript errors
3. Verify database connectivity
4. Check server error logs

---

## Conclusion

The WhimsicalFrog platform is designed to be completely customizable for any business type. By leveraging the database-driven configuration system and CSS variables, you can create a unique website that perfectly matches your brand and business needs.

The key to successful customization is to:
1. Start with the business settings configuration
2. Customize the visual styling through CSS variables
3. Add your content and products
4. Test thoroughly across devices
5. Launch and maintain regularly

With this system, you can transform WhimsicalFrog into any type of business website while maintaining all the powerful features like AI integration, inventory management, and e-commerce functionality. 