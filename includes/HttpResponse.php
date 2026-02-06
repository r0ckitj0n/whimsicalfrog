<?php
/**
 * HttpResponse class extracted from HttpClient
 */
class HttpResponse
{
    private $body;
    private $httpCode;
    private $info;

    public function __construct($body, $httpCode, $info)
    {
        $this->body = $body;
        $this->httpCode = $httpCode;
        $this->info = $info;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function json()
    {
        return json_decode($this->body, true);
    }

    public function getStatusCode()
    {
        return $this->httpCode;
    }

    public function isSuccess()
    {
        return $this->httpCode >= 200 && $this->httpCode < 300;
    }

    public function isError()
    {
        return !$this->isSuccess();
    }

    public function getInfo($key = null)
    {
        if ($key === null) {
            return $this->info;
        }
        return $this->info[$key] ?? null;
    }

    public function getHeaders()
    {
        return $this->getInfo('response_headers') ?? [];
    }

    public function getContentType()
    {
        return $this->getInfo('content_type');
    }

    public function getSize()
    {
        return $this->getInfo('size_download');
    }

    public function getTotalTime()
    {
        return $this->getInfo('total_time');
    }

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

    public function __toString()
    {
        return $this->body;
    }
}
