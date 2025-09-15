> Note: Technical Reference — Historical/Deep Technical context. For current behavior and routes, see documentation/ADMIN_GUIDE.md.

# WhimsicalFrog File Structure Organization

## Overview
This document outlines the organized file structure of the WhimsicalFrog application, ensuring proper separation of concerns, security, and maintainability.

## Directory Structure

### `/admin` - Administrative Interface
**Purpose**: Contains all administrative interface files
**Security**: Protected with .htaccess, requires admin authentication
**Files**:
- `admin.php` - Main admin dashboard
- `admin_*.php` - Specific admin sections (inventory, orders, customers, etc.)
- `room_config_manager.php` - Room configuration management
- `cost_breakdown_manager.php` - Cost management
- Database management tools

**Access Control**: 
- All files require admin authentication via `Auth::requireAdmin()`
- .htaccess prevents direct access to sensitive files
- Whitelist approach for allowed admin scripts

### `/api` - API Endpoints
**Purpose**: RESTful API endpoints for application functionality
**Security**: Protected with .htaccess, individual authentication per endpoint
**Files**:
- Core API files (`config.php`, `api_bootstrap.php`)
- Feature-specific APIs (inventory, orders, customers, etc.)
- Utility APIs (image processing, email, analytics)

**Access Control**:
- Individual endpoints implement appropriate authentication
- Admin endpoints use `AuthHelper::requireAdmin()`
- Public endpoints have rate limiting and validation

### `/includes` - Core PHP Libraries
**Purpose**: Reusable PHP classes and functions
**Security**: Protected with .htaccess, no direct access allowed
**Files**:
- `database.php` - Database connection management
- `auth.php`, `auth_helper.php` - Authentication systems
- `logger.php`, `admin_logger.php` - Logging systems
- Feature-specific helpers and managers

**Access Control**:
- Complete .htaccess protection prevents direct access
- Files are included by other scripts only

### `/functions` - Processing Functions
**Purpose**: Form processing and business logic
**Security**: Protected with .htaccess, admin authentication for sensitive operations
**Files**:
- Inventory processing (`process_inventory_*.php`)
- Order processing (`process_customer_orders.php`)
- Marketing functions (`process_email_campaign.php`, `process_social_post.php`)
- User management functions

**Access Control**:
- Critical functions require admin authentication
- User-facing functions (login, register) have appropriate validation
- .htaccess provides baseline protection

### `/logs` - Log Files
**Purpose**: Application and system logs
**Security**: Complete access denial via .htaccess
**Files**:
- `application.log` - General application logs
- `errors.log` - Error logs
- System monitoring logs

**Access Control**:
- Complete .htaccess protection denies all access
- Only accessible via admin logging interface
- Automatic cleanup and rotation

### `/css` - Stylesheets
**Purpose**: Application styling and themes
**Security**: Public access (required for web functionality)
**Files**:
- `bundle.css` - Compiled CSS bundle
- Component-specific stylesheets
- Core styling files

### `/js` - JavaScript Files
**Purpose**: Client-side functionality
**Security**: Public access (required for web functionality)
**Files**:
- `bundle.js` - Compiled JavaScript bundle
- Feature-specific JavaScript modules
- Core application scripts

### `/components` - Reusable Components
**Purpose**: Reusable PHP components and templates
**Security**: Public access (included by other scripts)
**Files**:
- Modal components
- Header and footer templates
- Reusable UI components

### `/images` - Static Assets
**Purpose**: Static image files and uploads
**Security**: Public access with upload restrictions
**Subdirectories**:
- `/backgrounds` - Background images
- `/items` - Product images
- `/logos` - Brand assets

## Security Implementation

### .htaccess Protection
Each sensitive directory has appropriate .htaccess protection:

1. **Admin Directory**: Prevents direct access, allows only specific admin scripts
2. **Includes Directory**: Complete access denial
3. **Functions Directory**: Basic protection with PHP-level authentication
4. **Logs Directory**: Complete access denial
5. **API Directory**: Security headers and basic protection

### Authentication Layers

1. **Session-Based Authentication**: For web interface users
2. **Token-Based Authentication**: For API access and development
3. **Admin Authentication**: Required for all administrative functions
4. **Function-Level Protection**: Critical processing functions require admin access

### File Organization Principles

1. **Separation of Concerns**: Each directory has a specific purpose
2. **Security by Default**: Sensitive files are protected by default
3. **Least Privilege**: Only necessary access is granted
4. **Centralized Management**: Common functionality is centralized

## Access Control Summary

### Admin-Only Access
- All files in `/admin`
- Critical processing functions in `/functions`
- Log management and viewing
- System configuration

### Public Access
- Static assets (`/css`, `/js`, `/images`)
- Public API endpoints
- User-facing components

### No Direct Access
- Core includes (`/includes`)
- Log files (`/logs`)
- Configuration files

## Logging and Monitoring

### File Logging
- **Location**: `/logs` directory
- **Primary Log**: `application.log`
- **Error Log**: `errors.log`
- **Rotation**: Automatic cleanup and rotation

### Database Logging
- **Primary Method**: Database-first logging approach
- **Tables**: `error_logs`, `analytics_logs`, `admin_activity_logs`, `email_logs`
- **Retention**: 90-day retention policy

### Admin Activity Tracking
- All admin actions are logged
- Comprehensive audit trail
- Real-time monitoring capabilities

## Maintenance and Cleanup

### Automated Processes
- Log rotation and cleanup
- Database log retention management
- Security audit capabilities

### Manual Processes
- Security audit script (`scripts/security-audit.php`)
- Log cleanup script (`scripts/cleanup-logs.php`)
- File structure validation

## Compliance and Best Practices

### Security Standards
- Defense in depth approach
- Principle of least privilege
- Regular security audits
- Comprehensive logging

### Development Standards
- Consistent file organization
- Centralized configuration
- Reusable components
- Clear separation of concerns

This file structure organization ensures security, maintainability, and scalability while following industry best practices for web application development.
