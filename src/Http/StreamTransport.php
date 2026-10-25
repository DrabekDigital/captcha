<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Http;

/**
 * Transport based on PHP streams, requires allow_url_fopen (fallback without curl)
 *
 * @internal
 */
class StreamTransport implements Transport
{
    public function __construct(
        private readonly int $timeout = 10
    ) {
    }

    public function post(string $url, array $data): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
                'timeout' => $this->timeout,
                // Client errors (e.g. invalid secret) still carry the JSON response with error codes
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            throw new HttpException('Failed to contact verification service: ' . (error_get_last()['message'] ?? 'unknown error'));
        }

        return $result;
    }
}
