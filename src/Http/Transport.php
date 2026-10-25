<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Http;

/**
 * HTTP transport used to contact the captcha verification endpoint
 *
 * @internal inject a PSR-18 client into CaptchaValidator to use your own HTTP client
 */
interface Transport
{
    /**
     * Send form-encoded POST request and return the response body
     *
     * @param string $url
     * @param array<string, string> $data
     * @return string
     * @throws HttpException when the verification service cannot be contacted
     */
    public function post(string $url, array $data): string;
}
