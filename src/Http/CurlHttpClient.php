<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Http;

/**
 * HTTP client based on the curl extension (default when available)
 */
class CurlHttpClient implements HttpClient
{
    public function __construct(
        private readonly int $timeout = 10
    ) {
    }

    public function post(string $url, array $data): string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new HttpException('Failed to initialize curl');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $result = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if (!is_string($result)) {
            throw new HttpException('Failed to contact verification service: ' . curl_error($handle));
        }

        // Client errors (e.g. invalid secret) still carry the JSON response with error codes
        if ($status >= 500) {
            throw new HttpException('Verification service responded with HTTP ' . $status);
        }

        return $result;
    }
}
