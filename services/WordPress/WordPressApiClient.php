<?php
/**
 * WordPress API Client
 * Low-level HTTP communication with WordPress sites
 */
class WordPressApiClient
{
    private $timeout = 15;  // seconds
    private $connectTimeout = 10;
    private $userAgent = 'SiteManager/1.0';

    public function __construct($timeout = 15, $connectTimeout = 10)
    {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Fetch diagnostics from WordPress site
     * 
     * @param string $wordpressUrl Base URL of WordPress site
     * @param string $apiKey API key for authentication
     * @return array Raw API response
     * @throws WordPressException
     */
    public function fetchDiagnostics($wordpressUrl, $apiKey)
    {
        if (empty($wordpressUrl) || empty($apiKey)) {
            throw new WordPressConfigurationException('WordPress URL and API Key are required');
        }

        // Build endpoint URL
        $url = rtrim($wordpressUrl, '/') . '/wp-json/fullmidia/v1/diagnostics';

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new WordPressConfigurationException('Invalid WordPress URL: ' . $wordpressUrl);
        }

        // Make request
        $response = $this->makeRequest($url, $apiKey);

        return $response;
    }

    /**
     * Make HTTP request with error handling
     * 
     * @param string $url Endpoint URL
     * @param string $apiKey API key
     * @return array Decoded response
     * @throws WordPressException
     */
    private function makeRequest($url, $apiKey)
    {
        $startTime = microtime(true);

        // Initialize cURL
        $ch = curl_init();

        if ($ch === false) {
            throw new WordPressNetworkException('Failed to initialize cURL');
        }

        try {
            // Set cURL options
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_HTTPHEADER => [
                    'X-Fullmidia-Key: ' . $apiKey,
                    'Accept: application/json',
                    'User-Agent: ' . $this->userAgent
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false
            ]);

            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrorNo = curl_errno($ch);

            $duration = round((microtime(true) - $startTime) * 1000);

            // Handle cURL errors
            if ($response === false) {
                if ($curlErrorNo == CURLE_OPERATION_TIMEDOUT || $curlErrorNo == CURLE_COULDNT_RESOLVE_HOST) {
                    throw new WordPressTimeoutException('Request timeout or DNS resolution failed after ' . $this->timeout . 's');
                } elseif ($curlErrorNo == CURLE_COULDNT_CONNECT) {
                    throw new WordPressUnreachableException('Could not connect to WordPress site');
                } else {
                    throw new WordPressNetworkException('cURL error: ' . $curlError);
                }
            }

            // Handle HTTP status codes
            if ($httpCode === 0) {
                throw new WordPressUnreachableException('No response from WordPress site');
            }

            if ($httpCode === 401) {
                throw new WordPressAuthenticationException('Unauthorized - Invalid API key (HTTP 401)');
            }

            if ($httpCode === 403) {
                throw new WordPressForbiddenException('Forbidden - API key may be restricted (HTTP 403)');
            }

            if ($httpCode === 404) {
                throw new WordPressUnreachableException('Endpoint not found (HTTP 404) - Fullmidia plugin may not be installed');
            }

            if ($httpCode >= 500) {
                throw new WordPressUnreachableException('WordPress server error (HTTP ' . $httpCode . ')');
            }

            if ($httpCode >= 400) {
                throw new WordPressUnreachableException('HTTP error: ' . $httpCode);
            }

            // Parse JSON response
            $decodedResponse = json_decode($response, true);
            
            if ($decodedResponse === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new WordPressInvalidResponseException('Invalid JSON response: ' . json_last_error_msg());
            }

            // Return with metadata
            return [
                'data' => $decodedResponse,
                'http_code' => $httpCode,
                'duration_ms' => $duration
            ];

        } finally {
            curl_close($ch);
        }
    }
}
