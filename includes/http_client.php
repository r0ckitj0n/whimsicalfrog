<?php

/**
 * Centralized HTTP Client
 * Handles cURL operations, API calls, and HTTP requests
 */


require_once __DIR__ . '/file_operations.php';
class HttpClient
{
    private $defaultOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'WhimsicalFrog/1.0'
    ];

    private $headers = [];
    private $options = [];
    // __construct function moved to constructor_manager.php for centralization

    /**
     * Set default headers
     */
    public function setHeaders(array $headers)
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Set a single header
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set cURL options
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Set authentication
     */
    public function setAuth($username, $password = null, $type = CURLAUTH_BASIC)
    {
        if ($password === null) {
            // Bearer token
            $this->setHeader('Authorization', 'Bearer ' . $username);
        } else {
            // Basic auth
            $this->options[CURLOPT_HTTPAUTH] = $type;
            $this->options[CURLOPT_USERPWD] = $username . ':' . $password;
        }
        return $this;
    }

    /**
     * Make GET request
     */
    public function get($url, $params = [])
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->makeRequest($url, 'GET');
    }

    /**
     * Make POST request
     */
    public function post($url, $data = null, $contentType = 'application/json')
    {
        $options = [CURLOPT_POST => true];

        if ($data !== null) {
            if ($contentType === 'application/json') {
                $options[CURLOPT_POSTFIELDS] = is_string($data) ? $data : json_encode($data);
                $this->setHeader('Content-Type', 'application/json');
            } elseif ($contentType === 'application/x-www-form-urlencoded') {
                $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
                $this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
            } else {
                $options[CURLOPT_POSTFIELDS] = $data;
                $this->setHeader('Content-Type', $contentType);
            }
        }

        return $this->makeRequest($url, 'POST', $options);
    }

    /**
     * Make PUT request
     */
    public function put($url, $data = null, $contentType = 'application/json')
    {
        $options = [CURLOPT_CUSTOMREQUEST => 'PUT'];

        if ($data !== null) {
            if ($contentType === 'application/json') {
                $options[CURLOPT_POSTFIELDS] = is_string($data) ? $data : json_encode($data);
                $this->setHeader('Content-Type', 'application/json');
            } else {
                $options[CURLOPT_POSTFIELDS] = $data;
                $this->setHeader('Content-Type', $contentType);
            }
        }

        return $this->makeRequest($url, 'PUT', $options);
    }
    // delete function moved to database_operations.php for centralization

    /**
     * Make PATCH request
     */
    public function patch($url, $data = null)
    {
        $options = [
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => is_string($data) ? $data : json_encode($data)
        ];

        $this->setHeader('Content-Type', 'application/json');

        return $this->makeRequest($url, 'PATCH', $options);
    }

    /**
     * Upload file
     */
    public function upload($url, $filePath, $fieldName = 'file', $additionalData = [])
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $data = $additionalData;
        $data[$fieldName] = new CURLFile($filePath);

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        ];

        return $this->makeRequest($url, 'POST', $options);
    }

    /**
     * Download file
     */
    public function download($url, $savePath)
    {
        $fp = fopen($savePath, 'w+');
        if (!$fp) {
            throw new Exception("Cannot open file for writing: $savePath");
        }

        $options = [CURLOPT_FILE => $fp];

        try {
            $response = $this->makeRequest($url, 'GET', $options);
            fclose($fp);
            return $response;
        } catch (Exception $e) {
            fclose($fp);
            if (file_exists($savePath)) {
                unlink($savePath);
            }
            throw $e;
        }
    }

    /**
     * Make the actual cURL request
     */
    private function makeRequest($url, $method, $additionalOptions = [])
    {
        $ch = curl_init();

        // Merge all options
        $options = array_merge($this->options, $additionalOptions);
        $options[CURLOPT_URL] = $url;

        // Set headers
        if (!empty($this->headers)) {
            $headerStrings = [];
            foreach ($this->headers as $key => $value) {
                $headerStrings[] = $key . ': ' . $value;
            }
            $options[CURLOPT_HTTPHEADER] = $headerStrings;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);

        curl_close($ch);

        if ($response === false) {
            throw new Exception("cURL Error: $error");
        }

        return new HttpResponse($response, $httpCode, $info);
    }

    /**
     * Create a new instance with specific configuration
     */
    public static function create($options = [])
    {
        return new self($options);
    }

    /**
     * Quick static methods for common operations
     */
    public static function quickGet($url, $headers = [])
    {
        return self::create()->setHeaders($headers)->get($url);
    }

    public static function quickPost($url, $data = null, $headers = [])
    {
        return self::create()->setHeaders($headers)->post($url, $data);
    }

    public static function quickPut($url, $data = null, $headers = [])
    {
        return self::create()->setHeaders($headers)->put($url, $data);
    }

    public static function quickDelete($url, $headers = [])
    {
        return self::create()->setHeaders($headers)->delete($url);
    }
}

/**
 * HTTP Response wrapper
 */
class HttpResponse
{
    private $body;
    private $httpCode;
    private $info;
    // __construct function moved to constructor_manager.php for centralization

    /**
     * Get response body
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Get response as JSON
     */
    public function json()
    {
        return json_decode($this->body, true);
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode()
    {
        return $this->httpCode;
    }

    /**
     * Check if request was successful
     */
    public function isSuccess()
    {
        return $this->httpCode >= 200 && $this->httpCode < 300;
    }

    /**
     * Check if request failed
     */
    public function isError()
    {
        return !$this->isSuccess();
    }

    /**
     * Get cURL info
     */
    public function getInfo($key = null)
    {
        if ($key === null) {
            return $this->info;
        }
        return $this->info[$key] ?? null;
    }

    /**
     * Get response headers
     */
    public function getHeaders()
    {
        return $this->getInfo('response_headers') ?? [];
    }

    /**
     * Get content type
     */
    public function getContentType()
    {
        return $this->getInfo('content_type');
    }

    /**
     * Get response size
     */
    public function getSize()
    {
        return $this->getInfo('size_download');
    }

    /**
     * Get total time
     */
    public function getTotalTime()
    {
        return $this->getInfo('total_time');
    }

    /**
     * Throw exception if request failed
     */
    public function throwIfError($message = null)
    {
        if ($this->isError()) {
            $errorMessage = $message ?? "HTTP Error {$this->httpCode}";
            if ($this->body) {
                $errorMessage .= ": " . $this->body;
            }
            throw new Exception($errorMessage);
        }
        return $this;
    }

    /**
     * Convert to string
     */
    public function __toString()
    {
        return $this->body;
    }
}

// Convenience functions
function http_get($url, $headers = [])
{
    return HttpClient::quickGet($url, $headers);
}

function http_post($url, $data = null, $headers = [])
{
    return HttpClient::quickPost($url, $data, $headers);
}

function http_put($url, $data = null, $headers = [])
{
    return HttpClient::quickPut($url, $data, $headers);
}

function http_delete($url, $headers = [])
{
    return HttpClient::quickDelete($url, $headers);
}

function http_client($options = [])
{
    return HttpClient::create($options);
}
