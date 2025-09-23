/**
 * Centralized API Client
 * Provides a unified interface for all API communications
 */

export class ApiClient {
  constructor(options = {}) {
    this.baseUrl = options.baseUrl || '';
    this.defaultHeaders = {
      'Content-Type': 'application/json',
      ...options.defaultHeaders
    };
    this.timeout = options.timeout || 30000; // 30 seconds
    this.retryAttempts = options.retryAttempts || 3;
    this.retryDelay = options.retryDelay || 1000; // 1 second
    this.requestInterceptors = [];
    this.responseInterceptors = [];
  }

  /**
   * Make HTTP request
   * @param {string} method - HTTP method (GET, POST, PUT, DELETE, etc.)
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async request(method, endpoint, options = {}) {
    const url = this.buildUrl(endpoint, options.query);
    const requestOptions = this.buildRequestOptions(method, options);

    // Apply request interceptors
    let processedOptions = requestOptions;
    for (const interceptor of this.requestInterceptors) {
      processedOptions = await interceptor(processedOptions);
    }

    let lastError;

    // Retry logic
    for (let attempt = 1; attempt <= this.retryAttempts; attempt++) {
      try {
        const response = await this.executeRequest(url, processedOptions);

        // Apply response interceptors
        let processedResponse = response;
        for (const interceptor of this.responseInterceptors) {
          processedResponse = await interceptor(processedResponse);
        }

        return processedResponse;
      } catch (error) {
        lastError = error;

        // Don't retry on 4xx errors (except 429)
        if (error.status >= 400 && error.status < 500 && error.status !== 429) {
          break;
        }

        // Wait before retry (except on last attempt)
        if (attempt < this.retryAttempts) {
          await this.delay(this.retryDelay * attempt);
        }
      }
    }

    throw lastError;
  }

  /**
   * Execute HTTP request
   * @param {string} url - Request URL
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async executeRequest(url, options) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.timeout);

    try {
      const response = await fetch(url, {
        ...options,
        signal: controller.signal
      });

      clearTimeout(timeoutId);

      const responseData = {
        status: response.status,
        statusText: response.statusText,
        headers: this.parseHeaders(response.headers),
        data: null,
        ok: response.ok
      };

      // Parse response based on content type
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        responseData.data = await response.json();
      } else {
        responseData.data = await response.text();
      }

      if (!response.ok) {
        throw {
          status: response.status,
          statusText: response.statusText,
          data: responseData.data,
          message: responseData.data?.message || `HTTP ${response.status}: ${response.statusText}`
        };
      }

      return responseData;
    } catch (error) {
      clearTimeout(timeoutId);

      if (error.name === 'AbortError') {
        throw { status: 408, message: 'Request timeout' };
      }

      throw error;
    }
  }

  /**
   * GET request
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async get(endpoint, options = {}) {
    return await this.request('GET', endpoint, options);
  }

  /**
   * POST request
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Request data
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async post(endpoint, data = null, options = {}) {
    return await this.request('POST', endpoint, { ...options, body: data });
  }

  /**
   * PUT request
   * @param {string} endpoint - API endpoint
   * @param {Object} data - Request data
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async put(endpoint, data = null, options = {}) {
    return await this.request('PUT', endpoint, { ...options, body: data });
  }

  /**
   * DELETE request
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Request options
   * @returns {Promise<Object>} Response data
   */
  async delete(endpoint, options = {}) {
    return await this.request('DELETE', endpoint, options);
  }

  /**
   * Build request URL
   * @param {string} endpoint - API endpoint
   * @param {Object} query - Query parameters
   * @returns {string} Complete URL
   */
  buildUrl(endpoint, query = {}) {
    const url = new URL(endpoint, this.baseUrl);

    Object.entries(query).forEach(([key, value]) => {
      if (value !== null && value !== undefined) {
        url.searchParams.append(key, value);
      }
    });

    return url.toString();
  }

  /**
   * Build request options
   * @param {string} method - HTTP method
   * @param {Object} options - Request options
   * @returns {Object} Request options
   */
  buildRequestOptions(method, options) {
    const requestOptions = {
      method: method.toUpperCase(),
      headers: { ...this.defaultHeaders, ...options.headers }
    };

    // Add body for non-GET requests
    if (method.toUpperCase() !== 'GET' && options.body) {
      if (options.body instanceof FormData) {
        // Remove Content-Type header for FormData (browser sets it automatically)
        delete requestOptions.headers['Content-Type'];
        requestOptions.body = options.body;
      } else if (typeof options.body === 'object') {
        requestOptions.body = JSON.stringify(options.body);
      } else {
        requestOptions.body = options.body;
      }
    }

    return requestOptions;
  }

  /**
   * Parse response headers
   * @param {Headers} headers - Response headers
   * @returns {Object} Parsed headers
   */
  parseHeaders(headers) {
    const parsed = {};
    headers.forEach((value, key) => {
      parsed[key] = value;
    });
    return parsed;
  }

  /**
   * Add request interceptor
   * @param {Function} interceptor - Request interceptor function
   */
  addRequestInterceptor(interceptor) {
    this.requestInterceptors.push(interceptor);
  }

  /**
   * Add response interceptor
   * @param {Function} interceptor - Response interceptor function
   */
  addResponseInterceptor(interceptor) {
    this.responseInterceptors.push(interceptor);
  }

  /**
   * Remove request interceptor
   * @param {Function} interceptor - Interceptor to remove
   */
  removeRequestInterceptor(interceptor) {
    const index = this.requestInterceptors.indexOf(interceptor);
    if (index > -1) {
      this.requestInterceptors.splice(index, 1);
    }
  }

  /**
   * Remove response interceptor
   * @param {Function} interceptor - Interceptor to remove
   */
  removeResponseInterceptor(interceptor) {
    const index = this.responseInterceptors.indexOf(interceptor);
    if (index > -1) {
      this.responseInterceptors.splice(index, 1);
    }
  }

  /**
   * Set authentication token
   * @param {string} token - Authentication token
   */
  setAuthToken(token) {
    if (token) {
      this.defaultHeaders['Authorization'] = `Bearer ${token}`;
    } else {
      delete this.defaultHeaders['Authorization'];
    }
  }

  /**
   * Set CSRF token
   * @param {string} token - CSRF token
   */
  setCsrfToken(token) {
    if (token) {
      this.defaultHeaders['X-CSRF-Token'] = token;
    } else {
      delete this.defaultHeaders['X-CSRF-Token'];
    }
  }

  /**
   * Enable debug logging
   */
  enableDebug() {
    this.addRequestInterceptor(async (options) => {
      console.log('[API Debug] Request:', options.method, options.url || 'N/A', options.body);
      return options;
    });

    this.addResponseInterceptor(async (response) => {
      console.log('[API Debug] Response:', response.status, response.data);
      return response;
    });
  }

  /**
   * Disable debug logging
   */
  disableDebug() {
    // Remove debug interceptors
    this.requestInterceptors = this.requestInterceptors.filter(
      interceptor => interceptor.name !== 'debugRequest'
    );
    this.responseInterceptors = this.responseInterceptors.filter(
      interceptor => interceptor.name !== 'debugResponse'
    );
  }

  /**
   * Delay helper
   * @param {number} ms - Milliseconds to delay
   * @returns {Promise} Delay promise
   */
  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Create configured API client instance
   * @param {Object} config - Configuration options
   * @returns {ApiClient} Configured API client
   */
  static create(config = {}) {
    return new ApiClient(config);
  }

  /**
   * Create API client for specific service
   * @param {string} serviceName - Service name
   * @param {Object} config - Service configuration
   * @returns {Object} Service API client
   */
  static createService(serviceName, config = {}) {
    const client = new ApiClient({
      baseUrl: config.baseUrl || `/api/${serviceName}`,
      ...config
    });

    // Add common interceptors for this service
    if (config.auth) {
      client.addRequestInterceptor(async (options) => {
        // Add service-specific authentication
        return options;
      });
    }

    return client;
  }
}
