> Note: Technical Reference — Historical/Deep Technical context. For current behavior and routes, see documentation/ADMIN_GUIDE.md.

# WhimsicalFrog Authentication System Documentation

## Overview
WhimsicalFrog uses a centralized, multi-layered authentication system that supports both session-based authentication for users and token-based authentication for APIs. The system has been standardized to use consistent password hashing and centralized database connections.

## Core Components

### 1. Database Configuration (`api/config.php`)
- **Environment Detection**: Automatically detects local vs production environments
- **Database Credentials**: 
  - Local: `localhost/whimsicalfrog` with `root/Palz2516`
  - Production: IONOS hosting with proper credentials
- **PDO Options**: Configured with proper error handling and UTF-8 support

### 2. Database Connection (`includes/database.php`)
- **Singleton Pattern**: Single database instance across the application
- **Connection Pooling**: Prevents multiple database connections
- **Helper Methods**: Query, insert, update methods for consistent database operations
- **Transaction Support**: Begin, commit, rollback for data integrity

### 3. Authentication System (`includes/auth.php`)
**Core Functions:**
- `isLoggedIn()`: Check if user has active session
- `getCurrentUser()`: Get user data from session
- `isAdmin()`: Check if user has admin role
- `loginUser($userData)`: Set user session data
- `logoutUser()`: Clear user session
- `requireAuth()`: Force authentication or redirect
- `requireAdmin()`: Force admin privileges or error
- `isAdminWithToken()`: Check admin with fallback token support

**Session Management:**
- Stores user data in `$_SESSION['user']` array
- Supports both array and JSON string formats
- Automatic session normalization
- Session cleanup on logout

### 4. Authentication Helper (`includes/auth_helper.php`)
**Class-based approach for API consistency:**
- `AuthHelper::isAdmin()`: Multi-source admin checking
- `AuthHelper::requireAdmin()`: Force admin or exit with JSON error
- `AuthHelper::getCurrentUser()`: Get current user data
- `AuthHelper::getAdminToken()`: Extract admin token from requests
- `AuthHelper::hasRole($role)`: Check specific user roles

**Token Sources Supported:**
- JSON request body: `{"admin_token": "whimsical_admin_2024"}`
- GET parameters: `?admin_token=whimsical_admin_2024`
- POST parameters: `admin_token=whimsical_admin_2024`

## Authentication Endpoints

### 1. Login Endpoint (`process_login.php`)
**Purpose**: Main login endpoint for frontend authentication
**Method**: POST
**Input**: JSON with `username` and `password`
**Features**:
- Proper password hashing verification using `password_verify()`
- Centralized database connection via `Database::getInstance()`
- Session management via `loginUser()` function
- Redirect URL support for post-login navigation
- Comprehensive error handling and logging

**Example Request**:
```bash
curl -X POST "http://localhost:8000/process_login.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Pass.123"}'
```

**Example Response**:
```json
{
  "userId": "F13001",
  "username": "admin",
  "email": "admin@whimsicalfrog.us",
  "role": "admin",
  "roleType": "admin",
  "firstName": "Jonathan",
  "lastName": "Graves",
  "redirectUrl": null
}
```

### 2. API Login Endpoint (`api/login.php`)
**Purpose**: Alternative login endpoint with same functionality
**Features**: Identical to `process_login.php` but in API directory
**Usage**: Can be used interchangeably with `process_login.php`

## Security Features

### 1. Password Security
- **Hashing**: All passwords stored using PHP's `password_hash()` function
- **Verification**: Login uses `password_verify()` for secure comparison
- **No Plain Text**: Passwords never stored or compared in plain text

### 2. Admin Token System
- **Development Token**: `whimsical_admin_2024` for API access
- **Multiple Sources**: Supports JSON, GET, and POST parameters
- **Fallback Authentication**: When session auth fails, token auth can succeed
- **API Consistency**: All admin APIs support token-based authentication

### 3. Session Security
- **Session Validation**: Automatic session format validation
- **Session Cleanup**: Invalid sessions automatically cleared
- **Role-based Access**: Consistent role checking across all endpoints
- **Session Fingerprinting**: Protection against session hijacking

## Database Schema

### Users Table Structure
```sql
CREATE TABLE users (
    id VARCHAR(10) PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,  -- bcrypt hashed
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    firstName VARCHAR(50),
    lastName VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Admin User Example
```sql
-- Username: admin
-- Password: Pass.123 (hashed)
-- Role: admin
-- Full access to all admin functions
```

## Implementation Standards

### 1. API Authentication Pattern
```php
// Include authentication
require_once __DIR__ . '/../includes/auth_helper.php';

// For admin-only endpoints
AuthHelper::requireAdmin();

// For optional admin checking
if (AuthHelper::isAdmin()) {
    // Admin-specific logic
}
```

### 2. Database Connection Pattern
```php
// Include database configuration
require_once __DIR__ . '/../api/config.php';

// Get database connection
$pdo = Database::getInstance();

// Use connection for queries
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
```

### 3. Frontend Authentication
```javascript
// Login request
fetch('/process_login.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        username: 'admin',
        password: 'Pass.123'
    })
})
.then(response => response.json())
.then(data => {
    if (data.userId) {
        // Login successful
        localStorage.setItem('user', JSON.stringify(data));
        // Redirect or update UI
    } else {
        // Handle login error
        console.error('Login failed:', data.error);
    }
});
```

## Migration from Old System

### Changes Made
1. **Replaced Plain Text Passwords**: Updated all login endpoints to use `password_verify()`
2. **Centralized Database**: All authentication uses `Database::getInstance()`
3. **Unified Token System**: Consistent admin token handling across all APIs
4. **Error Standardization**: Consistent error responses and logging
5. **Session Normalization**: Automatic session format validation and cleanup

### Backward Compatibility
- All existing function names maintained
- Session structures remain compatible
- API endpoints unchanged (just improved internally)
- Frontend code requires no changes

## Testing and Validation

### Login Test
```bash
# Test successful login
curl -X POST "http://localhost:8000/process_login.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Pass.123"}'

# Test failed login
curl -X POST "http://localhost:8000/process_login.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"wrong"}'
```

### Admin API Test
```bash
# Test with session (after login)
curl -X POST "http://localhost:8000/api/dashboard_sections.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"get_sections"}'

# Test with admin token
curl -X POST "http://localhost:8000/api/dashboard_sections.php" \
  -H "Content-Type: application/json" \
  -d '{"action":"get_sections","admin_token":"whimsical_admin_2024"}'
```

## Troubleshooting

### Common Issues
1. **Login 401 Errors**: Check password hashing in database
2. **Admin Access Denied**: Verify role is lowercase 'admin' in database
3. **Session Issues**: Clear browser cookies and try again
4. **Database Connection**: Check config.php environment detection

### Debug Information
```php
// Get authentication debug info
require_once 'includes/auth.php';
$debug = getAuthDebugInfo();
error_log(print_r($debug, true));
```

## Best Practices

### 1. API Development
- Always use `AuthHelper::requireAdmin()` for admin endpoints
- Include admin token support in all admin APIs
- Use centralized database connection via `Database::getInstance()`
- Implement proper error handling and logging

### 2. Security
- Never store passwords in plain text
- Always use `password_verify()` for login checks
- Validate admin tokens server-side
- Clear sessions on logout
- Log authentication failures for monitoring

### 3. Frontend Integration
- Store user data in localStorage after successful login
- Include proper error handling for all auth requests
- Clear stored user data on logout
- Handle session expiration gracefully

## Configuration Summary

**Login Endpoint**: `/process_login.php` (primary) or `/api/login.php` (alternative)
**Admin Token**: `whimsical_admin_2024`
**Database**: Centralized via `Database::getInstance()`
**Password Security**: bcrypt hashing via `password_hash()`/`password_verify()`
**Session Management**: Centralized via `includes/auth.php`
**API Authentication**: Multi-source token support via `AuthHelper`

The system is now fully standardized, secure, and consistent across all components. 