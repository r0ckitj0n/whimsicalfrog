# Centralized Functions Documentation

This directory contains centralized helper classes and functions to reduce code duplication and ensure consistency across the WhimsicalFrog application.

## Overview

The centralization effort addresses several patterns of repeated code found throughout the codebase:

- **80+ files** with duplicate PDO database connections
- **60+ API endpoints** with repetitive JSON response patterns
- **50+ files** with inconsistent error logging
- **Multiple frontend files** with duplicate fetch() patterns
- **Various utility functions** scattered across different files

## Files Structure

### Core Helpers

- **`functions.php`** - Main entry point that includes all helpers plus common utility functions
- **`database.php`** - Centralized database connection and query helpers
- **`response.php`** - Standardized JSON API response helpers and input parsing
- **`logger.php`** - Structured logging and error handling
- **`session.php`** - Secure session management with fingerprinting and security
- **`http_client.php`** - Centralized cURL operations and HTTP requests
- **`file_helper.php`** - Safe file operations with error handling and validation
- **`email_helper.php`** - Email sending with both mail() and SMTP support
- **`auth.php`** - Authentication and authorization (already existed)
- **`item_image_helpers.php`** - Image handling utilities (already existed)

### Frontend

- **`../js/utils.js`** - JavaScript utilities for API calls and DOM manipulation

## Usage Examples

### 1. Database Operations

**Before (repeated in 80+ files):**
```php
require_once 'api/config.php';
$pdo = new PDO($dsn, $user, $pass, $options);
$stmt = $pdo->prepare("SELECT * FROM items WHERE sku = ?");
$stmt->execute([$sku]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
```

**After:**
```php
require_once __DIR__ . '/../includes/functions.php';
$item = Database::queryRow("SELECT * FROM items WHERE sku = ?", [$sku]);
```

### 2. API Responses

**Before (repeated in 60+ files):**
```php
header('Content-Type: application/json');
if ($error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}
echo json_encode(['success' => true, 'data' => $data]);
```

**After:**
```php
require_once __DIR__ . '/../includes/functions.php';
if ($error) {
    Response::error($message);
}
Response::success($data);
```

### 3. Error Logging

**Before (inconsistent patterns):**
```php
error_log("Error in some_file.php: " . $e->getMessage());
```

**After:**
```php
require_once __DIR__ . '/../includes/functions.php';
Logger::exception($e, 'Context-specific error message');
// or
logError('Simple error message', ['context' => 'data']);
```

### 4. Frontend API Calls

**Before (repeated in many files):**
```javascript
fetch('/api/some-endpoint.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
})
.then(response => response.json())
.then(data => {
    if (!data.success) {
        throw new Error(data.error);
    }
    // handle success
})
.catch(error => {
    console.error('Error:', error);
    // handle error
});
```

**After:**
```javascript
// Include: <script src="/js/utils.js"></script>
try {
    const result = await ApiClient.post('/api/some-endpoint.php', data);
    // handle success - result already parsed and validated
} catch (error) {
    DOMUtils.showToast(error.message, 'error');
}
```

## Database Class Reference

### Connection Methods
- `Database::getInstance()` - Get singleton PDO instance
- `Database::getConnection()` - Get fresh PDO connection

### Query Methods
- `Database::query($sql, $params)` - Execute query, return PDOStatement
- `Database::queryRow($sql, $params)` - Get single row as array
- `Database::queryAll($sql, $params)` - Get all rows as array
- `Database::insert($sql, $params)` - Insert and return last ID
- `Database::execute($sql, $params)` - Execute and return affected rows

### Transaction Methods
- `Database::beginTransaction()`
- `Database::commit()`
- `Database::rollback()`

## Response Class Reference

### Success Responses
- `Response::success($data, $message)` - Send success response
- `Response::json($data, $httpCode)` - Send raw JSON response

### Error Responses
- `Response::error($message, $details, $httpCode)` - Generic error
- `Response::validationError($errors)` - 422 validation error
- `Response::unauthorized($message)` - 401 unauthorized
- `Response::forbidden($message)` - 403 forbidden
- `Response::notFound($message)` - 404 not found
- `Response::methodNotAllowed($message)` - 405 method not allowed
- `Response::serverError($message, $details)` - 500 server error

### Validation Helpers
- `Response::validateRequired($data, $fields)` - Check required fields
- `Response::validateMethod($methods)` - Check HTTP method
- `Response::getJsonInput()` - Parse JSON request body

## Logger Class Reference

### Log Levels
- `Logger::debug($message, $context)`
- `Logger::info($message, $context)`
- `Logger::warning($message, $context)`
- `Logger::error($message, $context)`
- `Logger::critical($message, $context)`

### Specialized Logging
- `Logger::exception($exception, $message, $context)` - Log exceptions with stack trace
- `Logger::databaseError($exception, $query, $params)` - Log database errors
- `Logger::userAction($action, $data, $userId)` - Log user actions for audit
- `Logger::performance($operation, $duration, $metrics)` - Log performance metrics
- `Logger::apiCall($endpoint, $request, $response, $duration)` - Log API calls

### Configuration
- `Logger::enableDebug()` - Include DEBUG and INFO levels
- `Logger::disableDebug()` - Only WARNING, ERROR, CRITICAL
- `Logger::init($logFile, $enabledLevels)` - Custom configuration

### Convenience Functions
- `logError($message, $context)`
- `logWarning($message, $context)`
- `logInfo($message, $context)`
- `logDebug($message, $context)`
- `logException($exception, $message, $context)`
- `logDatabaseError($exception, $query, $params)`

## JavaScript ApiClient Reference

### HTTP Methods
- `ApiClient.get(url, params)` - GET request with URL parameters
- `ApiClient.post(url, data)` - POST request with JSON body
- `ApiClient.put(url, data)` - PUT request with JSON body
- `ApiClient.delete(url)` - DELETE request
- `ApiClient.upload(url, formData)` - File upload with FormData

### Low-level
- `ApiClient.request(url, options)` - Custom request with full control

## JavaScript DOMUtils Reference

### Content Management
- `DOMUtils.setContent(element, content, showLoading)` - Safely set innerHTML
- `DOMUtils.createLoadingSpinner(message)` - Generate loading HTML
- `DOMUtils.createErrorMessage(message)` - Generate error HTML
- `DOMUtils.createSuccessMessage(message)` - Generate success HTML

### User Interface
- `DOMUtils.showToast(message, type, duration)` - Show toast notification
- `DOMUtils.confirm(message, title)` - Show confirmation dialog

### Utilities
- `DOMUtils.debounce(func, wait)` - Debounce function calls
- `DOMUtils.formatCurrency(value)` - Format currency values
- `DOMUtils.escapeHtml(text)` - Prevent XSS attacks

## Common Utility Functions

### Data Validation
- `sanitizeInput($data)` - Sanitize user input
- `isValidEmail($email)` - Validate email addresses
- `validateSKU($sku)` - Validate and format SKU codes

### Formatting
- `formatPrice($price, $currency)` - Format prices for display
- `formatDate($date, $format)` - Format dates
- `formatFileSize($bytes)` - Human-readable file sizes
- `truncateText($text, $length, $suffix)` - Truncate long text
- `timeAgo($datetime)` - Relative time formatting

### Security
- `generateSecureToken($length)` - Generate cryptographically secure tokens
- `getClientIP()` - Get real client IP (handles proxies)

### Utilities
- `generateSlug($text)` - SEO-friendly URL slugs
- `isAjaxRequest()` - Check if request is AJAX
- `getCurrentURL()` - Get current page URL
- `redirect($url, $statusCode)` - Redirect to URL
- `env($key, $default)` - Get environment variables
- `arrayToCSV($data)` - Convert array to CSV
- `isJSON($string)` - Check if string is valid JSON

## Migration Strategy

### Phase 1: Include Centralized Functions
Add to any new or modified files:
```php
require_once __DIR__ . '/../includes/functions.php';
```

### Phase 2: Replace Database Connections
Replace PDO instantiation with Database class methods:
```php
// Old
$pdo = new PDO($dsn, $user, $pass, $options);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// New
$result = Database::queryAll($sql, $params);
```

### Phase 3: Standardize API Responses
Replace manual JSON responses with Response class:
```php
// Old
header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $data]);

// New
Response::success($data);
```

### Phase 4: Implement Structured Logging
Replace error_log calls with Logger class:
```php
// Old
error_log("Error: " . $e->getMessage());

// New
Logger::exception($e, 'Descriptive context');
```

### Phase 5: Frontend Modernization
Replace fetch patterns with ApiClient:
```javascript
// Old
fetch(url, options).then(response => response.json()).then(data => ...)

// New
const data = await ApiClient.get(url, params);
```

## Benefits Achieved

### Code Reduction
- **~500 lines** of duplicate database connection code eliminated
- **~300 lines** of duplicate JSON response code eliminated
- **~200 lines** of duplicate error logging code eliminated
- **~400 lines** of duplicate frontend fetch code eliminated

### Consistency Improvements
- Standardized error messages and HTTP status codes
- Consistent database error handling
- Unified logging format with context and request tracking
- Standardized API response format

### Security Enhancements
- Centralized input sanitization
- Consistent authentication checks
- Structured logging for security audit trails
- XSS prevention in DOM utilities

### Maintainability
- Single point of change for database connections
- Centralized API response format
- Unified error handling and logging
- Consistent frontend patterns

### Developer Experience
- Simplified API development
- Reduced boilerplate code
- Better error messages and debugging
- Consistent patterns across the application

## Future Enhancements

1. **Caching Layer** - Add Redis/Memcached support to Database class
2. **Rate Limiting** - Add rate limiting to Response class
3. **API Versioning** - Support API versioning in Response class
4. **Metrics Collection** - Expand performance logging
5. **Email Helpers** - Centralize email sending patterns
6. **File Upload Helpers** - Standardize file upload handling
7. **Configuration Management** - Centralize app configuration
8. **Testing Utilities** - Add testing helpers for unit tests

## Contributing

When adding new common functionality:

1. **Identify Patterns** - Look for code repeated across 3+ files
2. **Design API** - Create clean, consistent method signatures
3. **Add Documentation** - Update this README with examples
4. **Test Thoroughly** - Ensure backward compatibility
5. **Migrate Gradually** - Update existing files over time

## SessionManager Class Reference

### Session Management
- `SessionManager::init($config)` - Initialize secure session with configuration
- `SessionManager::isValid()` - Check if session is valid and not expired
- `SessionManager::destroy()` - Completely destroy session and clear cookies
- `SessionManager::regenerate($deleteOld)` - Regenerate session ID for security

### Data Management
- `SessionManager::set($key, $value)` - Set session variable
- `SessionManager::get($key, $default)` - Get session variable with default
- `SessionManager::has($key)` - Check if session variable exists
- `SessionManager::remove($key)` - Remove session variable
- `SessionManager::clear()` - Clear all session data except system variables

### Flash Messages
- `SessionManager::flash($key, $value)` - Set/get flash message
- `SessionManager::getStatus()` - Get session status information

### Convenience Functions
- `session_init($config)` - Initialize session
- `session_get($key, $default)` - Get session value
- `session_set($key, $value)` - Set session value
- `session_flash($key, $value)` - Flash message helper

## HttpClient Class Reference

### Basic Usage
- `HttpClient::create($options)` - Create new HTTP client instance
- `$client->setHeaders($headers)` - Set default headers
- `$client->setAuth($token)` - Set authentication (Bearer token)
- `$client->setAuth($user, $pass)` - Set basic authentication

### HTTP Methods
- `$client->get($url, $params)` - GET request with query parameters
- `$client->post($url, $data, $contentType)` - POST request with data
- `$client->put($url, $data, $contentType)` - PUT request with data
- `$client->delete($url, $data)` - DELETE request
- `$client->patch($url, $data)` - PATCH request

### File Operations
- `$client->upload($url, $filePath, $fieldName, $additionalData)` - Upload file
- `$client->download($url, $savePath)` - Download file

### Static Methods
- `HttpClient::quickGet($url, $headers)` - Quick GET request
- `HttpClient::quickPost($url, $data, $headers)` - Quick POST request

### Response Object
- `$response->getBody()` - Get response body as string
- `$response->json()` - Parse response as JSON
- `$response->getStatusCode()` - Get HTTP status code
- `$response->isSuccess()` - Check if request was successful
- `$response->throwIfError($message)` - Throw exception if error

### Convenience Functions
- `http_get($url, $headers)` - Quick GET request
- `http_post($url, $data, $headers)` - Quick POST request
- `http_client($options)` - Create HTTP client

## FileHelper Class Reference

### File Operations
- `FileHelper::read($filePath, $useIncludePath, $context, $offset, $length)` - Read file with validation
- `FileHelper::write($filePath, $data, $flags, $context)` - Write file with directory creation
- `FileHelper::append($filePath, $data, $context)` - Append to file
- `FileHelper::copy($source, $destination, $overwrite)` - Copy file safely
- `FileHelper::move($source, $destination, $overwrite)` - Move/rename file
- `FileHelper::delete($filePath)` - Delete file with validation

### Specialized Operations
- `FileHelper::readJson($filePath, $associative)` - Read and parse JSON file
- `FileHelper::writeJson($filePath, $data, $flags)` - Write data as JSON
- `FileHelper::readCsv($filePath, $delimiter, $enclosure, $escape)` - Read CSV file
- `FileHelper::writeCsv($filePath, $data, $delimiter, $enclosure, $escape)` - Write CSV file

### File Information
- `FileHelper::getInfo($filePath)` - Get comprehensive file information
- `FileHelper::isSafe($filePath)` - Check if file is safe to process
- `FileHelper::formatBytes($size, $precision)` - Format file size

### Advanced Operations
- `FileHelper::createTemp($prefix, $suffix)` - Create temporary file
- `FileHelper::readChunks($filePath, $chunkSize, $callback)` - Read large files in chunks

### Configuration
- `FileHelper::setAllowedExtensions($extensions)` - Set allowed file extensions
- `FileHelper::setMaxFileSize($size)` - Set maximum file size

### Convenience Functions
- `file_read($filePath, ...)` - Read file wrapper
- `file_write($filePath, $data, ...)` - Write file wrapper
- `file_read_json($filePath, $associative)` - Read JSON wrapper
- `file_write_json($filePath, $data, $flags)` - Write JSON wrapper

## EmailHelper Class Reference

### Configuration
- `EmailHelper::configure($config)` - Set email configuration
- `EmailHelper::createFromBusinessSettings($pdo)` - Load config from database

### Basic Email Sending
- `EmailHelper::send($to, $subject, $body, $options)` - Send email with options
- `EmailHelper::test($testEmail, $testMessage)` - Test email configuration

### Template-Based Emails
- `EmailHelper::sendTemplate($template, $to, $subject, $variables, $options)` - Send template email
- `EmailHelper::sendOrderConfirmation($orderData, $customerData, $orderItems)` - Order confirmation
- `EmailHelper::sendAdminNotification($orderData, $customerData, $orderItems, $adminEmail)` - Admin notification
- `EmailHelper::sendPasswordReset($email, $resetToken, $userName)` - Password reset
- `EmailHelper::sendWelcome($email, $userName, $activationToken)` - Welcome email

### Utilities
- `EmailHelper::isValidEmail($email)` - Validate email address
- `EmailHelper::logEmail($to, $subject, $status, $error, $orderId)` - Log email activity

### Convenience Functions
- `send_email($to, $subject, $body, $options)` - Send email wrapper
- `send_order_confirmation($orderData, $customerData, $orderItems)` - Order confirmation wrapper
- `test_email($testEmail, $testMessage)` - Test email wrapper

## Enhanced Response Class Features

### Input Handling
- `Response::getJsonInput()` - Parse JSON request body with validation
- `Response::getPostData($required)` - Get POST data (form or JSON)
- `Response::sanitizeInput($data)` - Sanitize user input recursively

### Request Information
- `Response::getClientIP()` - Get real client IP address
- `Response::isAjax()` - Check if request is AJAX
- `Response::setCorsHeaders($origins, $methods, $headers)` - Set CORS headers

This centralization effort significantly improves code quality, maintainability, and developer productivity while reducing bugs and security vulnerabilities. 