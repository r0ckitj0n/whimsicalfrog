# WhimsicalFrog Template System Documentation

## Overview

The WhimsicalFrog Template System provides a comprehensive, admin-controlled CSS framework for all major UI components. This system allows complete customization of your website's appearance through the Admin Panel without requiring code modifications.

## 🎯 Key Features

- **119 CSS Variables**: Complete control over all visual aspects
- **9 Component Categories**: Header, Buttons, Forms, Cards, Typography, Layout, Colors, Animations, and Admin
- **Admin Panel Integration**: All styles controllable through Global CSS Rules
- **Responsive Design**: Mobile-first approach with breakpoints
- **Accessibility Compliant**: WCAG guidelines and keyboard navigation
- **Print Optimized**: Dedicated print styles
- **Performance Optimized**: Efficient CSS with browser caching

## 📁 File Structure

```
css/
├── styles.css              # Main CSS with utility classes
├── header-styles.css       # Header/Navigation system
├── button-styles.css       # Button system
├── form-styles.css         # Form system
├── card-styles.css         # Card/Product system
├── footer-styles.css       # Footer system
└── global-modals.css       # Modal system

components/
├── header_template.php     # Header component
├── button_template.php     # Button component functions
├── simple_footer.php       # Simple footer component
└── footer_template.php     # Advanced footer component
```

## 🎨 CSS Categories & Variables

### 1. Header System (11 variables)
Controls navigation, logo, search bar, and header layout.

**Key Variables:**
- `--header-bg-color`: Header background color
- `--header-text-color`: Header text color
- `--nav-link-hover-color`: Navigation link hover color
- `--search-bar-bg`: Search bar background
- `--header-shadow`: Header drop shadow

**CSS Classes:**
```css
.site-header          /* Main header container */
.header-logo          /* Logo styling */
.nav-link             /* Navigation links */
.nav-link.active      /* Active navigation state */
.search-bar           /* Search input field */
.cart-link            /* Shopping cart link */
```

### 2. Button System (15 variables)
Comprehensive button styling with multiple variants and states.

**Key Variables:**
- `--btn-primary-bg`: Primary button background
- `--btn-secondary-color`: Secondary button text color
- `--btn-danger-bg`: Danger button background
- `--btn-padding`: Button padding
- `--btn-border-radius`: Button border radius

**CSS Classes:**
```css
.btn                  /* Base button class */
.btn-primary          /* Primary button */
.btn-secondary        /* Secondary button */
.btn-danger           /* Danger button */
.btn-success          /* Success button */
.btn-warning          /* Warning button */
.btn-info             /* Info button */
.btn-sm, .btn-lg      /* Size variants */
.btn-outline          /* Outline variant */
.btn-ghost            /* Ghost variant */
.btn-block            /* Full width button */
.btn-loading          /* Loading state */
.btn-icon             /* Icon-only button */
```

### 3. Form System (13 variables)
Complete form styling including inputs, labels, validation states.

**Key Variables:**
- `--form-input-bg`: Input background color
- `--form-input-border-focus`: Focus border color
- `--form-label-color`: Label text color
- `--form-error-color`: Error message color
- `--form-success-color`: Success message color

**CSS Classes:**
```css
.form-input           /* Text inputs */
.form-label           /* Form labels */
.form-select          /* Select dropdowns */
.form-textarea        /* Textarea fields */
.form-check           /* Checkboxes/radios */
.form-switch          /* Toggle switches */
.form-file            /* File inputs */
.form-error           /* Error messages */
.form-success         /* Success messages */
.input-group          /* Input groups */
```

### 4. Card System (13 variables)
Product cards, content cards, and grid layouts.

**Key Variables:**
- `--card-bg`: Card background color
- `--card-shadow`: Card drop shadow
- `--card-shadow-hover`: Hover shadow effect
- `--product-price-color`: Product price color
- `--product-image-border-radius`: Product image radius

**CSS Classes:**
```css
.card                 /* Base card class */
.card-title           /* Card titles */
.card-text            /* Card body text */
.product-card         /* Product-specific cards */
.product-image        /* Product images */
.product-price        /* Product pricing */
.product-badge        /* Sale/New badges */
.card-grid-2          /* 2-column grid */
.card-grid-3          /* 3-column grid */
.card-grid-4          /* 4-column grid */
```

### 5. Typography System (13 variables)
Font families, sizes, weights, and line heights.

**Key Variables:**
- `--heading-font-family`: Heading font (Merienda)
- `--body-font-family`: Body font (Inter)
- `--heading-h1-size`: H1 font size
- `--body-text-size`: Body text size
- `--line-height-normal`: Standard line height

**CSS Classes:**
```css
.h1, .h2, .h3, .h4    /* Heading classes */
.body-text            /* Body text */
.small-text           /* Small text */
```

### 6. Layout System (15 variables)
Spacing, containers, grids, and responsive breakpoints.

**Key Variables:**
- `--container-max-width`: Maximum container width
- `--section-padding-y`: Section vertical padding
- `--grid-gap`: Grid gap spacing
- `--spacing-md`: Medium spacing unit
- `--border-radius-lg`: Large border radius

**CSS Classes:**
```css
.container            /* Main container */
.section              /* Section spacing */
.grid                 /* CSS Grid */
.grid-cols-2          /* 2-column grid */
.flex                 /* Flexbox */
.m-md, .p-lg          /* Spacing utilities */
.rounded-lg           /* Border radius */
```

### 7. Color System (20 variables)
Complete color palette with semantic color names.

**Key Variables:**
- `--color-primary`: Primary brand color (#87ac3a)
- `--color-success`: Success color
- `--color-error`: Error color
- `--color-gray-500`: Mid-tone gray
- `--color-gray-100`: Light gray

**CSS Classes:**
```css
.text-primary         /* Primary text color */
.text-success         /* Success text color */
.bg-primary           /* Primary background */
.bg-error             /* Error background */
```

### 8. Animation System (8 variables)
Transitions, hover effects, and animations.

**Key Variables:**
- `--transition-normal`: Standard transition (0.2s ease)
- `--hover-scale`: Hover scale transform
- `--hover-lift`: Hover lift transform
- `--animation-fade-in`: Fade in animation

**CSS Classes:**
```css
.transition-normal    /* Normal transition */
.hover-scale          /* Scale on hover */
.hover-lift           /* Lift on hover */
```

### 9. Admin Interface System (12 variables)
Admin panel specific styling.

**Key Variables:**
- `--admin-bg-color`: Admin background
- `--admin-header-bg`: Admin header background
- `--admin-nav-active-bg`: Active nav background
- `--admin-success-bg`: Success message background

**CSS Classes:**
```css
.admin-container      /* Admin page container */
.admin-sidebar        /* Admin sidebar */
.admin-header         /* Admin header */
.admin-nav-item       /* Admin navigation items */
.admin-success        /* Success messages */
.admin-error          /* Error messages */
```

## 🔧 Usage Guide

### Basic Implementation

1. **Include CSS Files**
```html
<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="css/header-styles.css">
<link rel="stylesheet" href="css/button-styles.css">
<link rel="stylesheet" href="css/form-styles.css">
<link rel="stylesheet" href="css/card-styles.css">
<link rel="stylesheet" href="css/footer-styles.css">
```

2. **Use Template Components**
```php
<?php include 'components/header_template.php'; ?>

<main class="container section">
    <div class="card-grid-3">
        <?php foreach ($products as $product): ?>
            <div class="product-card">
                <img src="<?php echo $product['image']; ?>" class="product-image">
                <div class="product-card-body">
                    <h3 class="product-title"><?php echo $product['name']; ?></h3>
                    <p class="product-description"><?php echo $product['description']; ?></p>
                    <div class="product-price">$<?php echo $product['price']; ?></div>
                    <div class="product-actions">
                        <?php echo render_button(['text' => 'Add to Cart', 'type' => 'primary']); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php include 'components/simple_footer.php'; ?>
```

### Header Component Configuration

```php
<?php
$header_config = [
    'show_search' => true,
    'show_cart' => true,
    'show_user_menu' => true,
    'logo_text' => 'Your Store',
    'logo_tagline' => 'Amazing Products',
    'navigation_items' => [
        ['label' => 'Home', 'url' => '/', 'active' => true],
        ['label' => 'Shop', 'url' => '/shop', 'active' => false],
        ['label' => 'About', 'url' => '/about', 'active' => false],
    ],
    'search_placeholder' => 'Search products...'
];
include 'components/header_template.php';
?>
```

### Button Component Usage

```php
<?php
// Include the button component
include 'components/button_template.php';

// Basic buttons
echo render_button(['text' => 'Click Me']);
echo render_button(['text' => 'Save', 'type' => 'primary', 'icon' => 'save']);
echo render_button(['text' => 'Delete', 'type' => 'danger', 'icon' => 'trash']);

// Button groups
echo render_button_group([
    ['text' => 'Edit', 'type' => 'secondary'],
    ['text' => 'Delete', 'type' => 'danger']
]);

// Form buttons
echo render_form_buttons([
    'submit_text' => 'Save Changes',
    'cancel_href' => '/dashboard'
]);

// CRUD buttons
echo render_crud_buttons([
    'edit_href' => '/edit/123',
    'delete_href' => '/delete/123'
]);
?>
```

### Form Implementation

```html
<form class="form-container">
    <div class="form-group">
        <label class="form-label required">Name</label>
        <input type="text" class="form-input" placeholder="Enter your name">
        <div class="form-error">This field is required</div>
    </div>
    
    <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" class="form-input" placeholder="Enter your email">
    </div>
    
    <div class="form-group">
        <label class="form-label">Message</label>
        <textarea class="form-input form-textarea" placeholder="Enter your message"></textarea>
    </div>
    
    <div class="form-actions">
        <?php echo render_form_buttons(); ?>
    </div>
</form>
```

### Card Layouts

```html
<!-- Product Grid -->
<div class="card-grid-3">
    <div class="product-card">
        <div class="product-card-image">
            <img src="product1.jpg" class="product-image">
            <div class="product-badge sale">Sale</div>
        </div>
        <div class="product-card-body">
            <h3 class="product-title">Amazing Product</h3>
            <p class="product-description">This is an amazing product description.</p>
            <div class="product-pricing">
                <span class="product-price">$29.99</span>
                <span class="product-price-original">$39.99</span>
            </div>
            <div class="product-actions">
                <button class="btn btn-primary btn-block">Add to Cart</button>
            </div>
        </div>
    </div>
</div>

<!-- Content Cards -->
<div class="card-grid-2">
    <div class="card">
        <div class="card-header">
            <h3 class="card-header-title">Card Title</h3>
        </div>
        <div class="card-body">
            <p class="card-text">Card content goes here.</p>
        </div>
        <div class="card-footer">
            <button class="btn btn-primary">Action</button>
        </div>
    </div>
</div>
```

## 🎛️ Admin Panel Control

### Accessing Global CSS Rules

1. Navigate to **Admin Settings** → **Global CSS Rules**
2. Select the category you want to customize:
   - **Header**: Navigation and header styling
   - **Buttons**: All button types and states
   - **Forms**: Input fields, labels, validation
   - **Cards**: Product cards and content cards
   - **Typography**: Fonts, sizes, line heights
   - **Layout**: Spacing, containers, grids
   - **Colors**: Complete color palette
   - **Animations**: Transitions and effects
   - **Admin**: Admin interface styling
   - **Footer**: Footer components

### Making Changes

1. **Select a Variable**: Click on any CSS rule to edit
2. **Update Value**: Change colors, sizes, fonts, etc.
3. **Preview**: Changes apply immediately
4. **Save**: Click "Update CSS Rules" to save changes

### Common Customizations

**Change Primary Color:**
- Find `color-primary` in Colors category
- Update value (e.g., `#ff6b35` for orange)
- All primary buttons, links, and accents update automatically

**Adjust Typography:**
- Update `heading-font-family` for different heading fonts
- Modify `body-text-size` for overall text scaling
- Change `line-height-normal` for text spacing

**Customize Spacing:**
- Modify `container-max-width` for layout width
- Update `section-padding-y` for vertical spacing
- Adjust `grid-gap` for card/grid spacing

## 📱 Responsive Design

The system uses a mobile-first approach with these breakpoints:

- **Mobile**: < 480px
- **Tablet**: 481px - 768px
- **Desktop**: 769px - 1024px
- **Large Desktop**: > 1024px

### Responsive Classes

```css
/* Grid automatically collapses on mobile */
.card-grid-4  /* 4 cols desktop → 3 cols tablet → 1 col mobile */
.card-grid-3  /* 3 cols desktop → 2 cols tablet → 1 col mobile */
.card-grid-2  /* 2 cols desktop → 1 col mobile */

/* Container adapts padding */
.container    /* Full width on mobile with appropriate padding */

/* Typography scales */
h1, h2, h3    /* Automatically scale down on smaller screens */
```

## ♿ Accessibility Features

### Built-in Accessibility

- **Keyboard Navigation**: All interactive elements are keyboard accessible
- **ARIA Labels**: Proper ARIA attributes for screen readers
- **Color Contrast**: High contrast mode support
- **Reduced Motion**: Respects `prefers-reduced-motion` setting
- **Focus Indicators**: Clear focus states for all interactive elements
- **Semantic HTML**: Proper heading hierarchy and landmarks

### Accessibility Classes

```css
.sr-only              /* Screen reader only content */
.focus-visible        /* Enhanced focus indicators */
.high-contrast        /* High contrast mode adaptations */
```

## 🖨️ Print Optimization

All components include print-specific styles:

- **Simplified Layouts**: Complex layouts simplified for print
- **Hidden Elements**: Interactive elements hidden in print
- **Optimized Colors**: Colors optimized for print readability
- **Page Breaks**: Proper page break handling

## 🔧 Customization Examples

### Custom Color Scheme

```css
/* Dark theme example - set in admin panel */
--header-bg-color: #1f2937;
--header-text-color: #f9fafb;
--card-bg: #374151;
--card-text-color: #e5e7eb;
--color-primary: #10b981;
```

### Custom Typography

```css
/* Modern typography - set in admin panel */
--heading-font-family: 'Poppins', sans-serif;
--body-font-family: 'Inter', sans-serif;
--heading-h1-size: 3rem;
--body-text-size: 1.125rem;
--line-height-relaxed: 1.8;
```

### Custom Spacing

```css
/* Tight spacing - set in admin panel */
--container-max-width: 1000px;
--section-padding-y: 2rem;
--grid-gap: 1rem;
--card-padding: 1rem;
```

## 🚀 Performance Optimization

### CSS Loading Strategy

1. **Critical CSS**: Core styles loaded first
2. **Component CSS**: Loaded as needed
3. **Utility Classes**: Comprehensive utility system
4. **Caching**: Aggressive browser caching enabled

### Best Practices

- **Minimize HTTP Requests**: Combine CSS files when possible
- **Use Utility Classes**: Avoid inline styles
- **Leverage Caching**: CSS variables enable efficient updates
- **Optimize Images**: Use appropriate formats and sizes

## 🔍 Troubleshooting

### Common Issues

**Styles Not Applying:**
1. Check CSS file inclusion order
2. Verify CSS variable names match database
3. Clear browser cache
4. Check for CSS conflicts

**Mobile Layout Issues:**
1. Verify viewport meta tag: `<meta name="viewport" content="width=device-width, initial-scale=1">`
2. Test responsive classes
3. Check media query conflicts

**Admin Panel Changes Not Showing:**
1. Clear browser cache
2. Check database connection
3. Verify CSS variable syntax
4. Test in incognito mode

### Debug Mode

Enable debug mode to see CSS variable values:

```css
/* Add to admin panel for debugging */
--debug-mode: 1;
```

## 📈 Future Enhancements

### Planned Features

- **Theme Presets**: Pre-configured color schemes
- **Component Builder**: Visual component designer
- **CSS Export**: Export custom CSS for external use
- **Version Control**: CSS change history and rollback
- **A/B Testing**: Test different style variations

### Extensibility

The system is designed for easy extension:

1. **Add New Categories**: Create new CSS variable categories
2. **Custom Components**: Build new template components
3. **Third-party Integration**: Easy integration with external libraries
4. **API Access**: Programmatic access to CSS variables

## 📚 Additional Resources

### Documentation Files

- `MODAL_STANDARDIZATION_COMPLETE.md`: Modal system documentation
- `FOOTER_SYSTEM_DOCUMENTATION.md`: Footer system details
- Component-specific README files in `/components/`

### Support

For technical support or questions:
1. Check this documentation first
2. Review component-specific docs
3. Test in a clean environment
4. Contact development team with specific error details

---

**Last Updated**: December 2024  
**Version**: 2.0  
**Total CSS Variables**: 119  
**Component Categories**: 9  
**Template Components**: 4+ 