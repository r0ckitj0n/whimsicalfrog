> Note: Technical Reference — Historical/Deep Technical context. For current behavior and routes, see documentation/ADMIN_GUIDE.md.

# WhimsicalFrog Standardized CSS Classes

## Overview
This document outlines the standardized CSS classes created for the WhimsicalFrog website. These classes replace Tailwind utility classes and provide a consistent, reusable styling system.

## Gradient Classes

### Header Gradients
- `.header-gradient-fade`: Creates a dark-to-transparent vertical gradient overlay for page headers
- `.header-gradient-brand`: Creates a brand-color gradient (green to darker green)

### Background Gradients
- `.bg-gradient-brand-primary`: Linear gradient from brand primary to secondary color
- `.bg-gradient-brand-horizontal`: Horizontal gradient from brand primary to lighter variant

## Layout Classes
- `.page-header-container`: Standard container for page headers with proper positioning
- `.page-content-container`: Standard content container with appropriate padding
- `.fullscreen-container`: Full-width container that extends to viewport edges

## Overlay Classes
- `.overlay-gradient-top`: Dark fade overlay at the top of elements
- `.overlay-gradient-bottom`: Dark fade overlay at the bottom of elements

## Text Classes
- `.text-brand-primary`: Text in brand primary color
- `.text-brand-secondary`: Text in brand secondary color
- `.text-shadow-dark`: Dark text shadow for better readability on light backgrounds

## Z-Index Classes
- `.z-base`: Base z-index (1)
- `.z-content-overlay`: Content overlay z-index (10)
- `.z-dropdown`: Dropdown z-index (100)
- `.z-navigation`: Navigation z-index (200)
- `.z-header`: Header z-index (1000)
- `.z-modal-backdrop`: Modal backdrop z-index (300)
- `.z-modal`: Modal z-index (400)
- `.z-notification`: Notification z-index (500)
- `.z-tooltip`: Tooltip z-index (600)

## Utility Classes
- `.transition-smooth`: Standard transition effect (0.3s ease)
- `.rounded-brand`: Standard border radius using brand settings
- `.shadow-brand`: Standard box shadow using brand settings

## Component Classes
- `.btn-brand`: Standard brand button styling
- `.card-standard`: Standard card component styling

These classes should be used throughout the site to ensure consistency and maintainability.
