<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha;

use DrabekDigital\Captcha\Enums\CaptchaType;
use DrabekDigital\Captcha\Http\CurlHttpClient;
use DrabekDigital\Captcha\Http\HttpClient;
use DrabekDigital\Captcha\Http\StreamHttpClient;
use Nette\Http\IRequest;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * Server-side validator for Captcha/Turnstile/hCaptcha responses
 */
class CaptchaValidator
{
    /**
     * Default verification URLs
     */
    private const VERIFY_URLS = [
        CaptchaType::TURNSTILE->value => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        CaptchaType::HCAPTCHA->value => 'https://hcaptcha.com/siteverify',
    ];

    private readonly HttpClient $httpClient;

    public function __construct(
        private readonly string $secretKey,
        private readonly CaptchaType $type = CaptchaType::TURNSTILE,
        private readonly ?string $verifyUrl = null,
        private readonly ?IRequest $httpRequest = null,
        ?HttpClient $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? (extension_loaded('curl') ? new CurlHttpClient() : new StreamHttpClient());
    }

    /**
     * Verify captcha response
     *
     * @param string $response
     * @param string|null $remoteIp
     * @return bool
     */
    public function verify(string $response, ?string $remoteIp = null): bool
    {
        if ($response === '') {
            return false;
        }

        $verifyUrl = $this->getVerifyUrl();

        $postData = $this->buildPostData($response, $remoteIp);
        
        try {
            $result = $this->httpClient->post($verifyUrl, $postData);
            return $this->parseResponse($result);
        } catch (\Exception $e) {
            error_log('Captcha verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get verification URL for the captcha type
     *
     * @return string
     */
    private function getVerifyUrl(): string
    {
        if ($this->verifyUrl !== null && $this->verifyUrl !== '') {
            return $this->verifyUrl;
        }

        return self::VERIFY_URLS[$this->type->value];
    }

    /**
     * Build POST data for verification request
     *
     * @param string $response
     * @param string|null $remoteIp
     * @return array<string, string>
     */
    private function buildPostData(string $response, ?string $remoteIp = null): array
    {
        $data = [
            'secret' => $this->secretKey,
            'response' => $response,
        ];

        // Add remote IP if available
        if ($remoteIp === null || $remoteIp === '') {
            $remoteIp = $this->httpRequest?->getRemoteAddress();
        }
        if ($remoteIp !== null && $remoteIp !== '') {
            $data['remoteip'] = $remoteIp;
        }

        return $data;
    }

    /**
     * Parse verification response
     *
     * @param string $response
     * @return bool
     */
    private function parseResponse(string $response): bool
    {
        try {
            $data = Json::decode($response, forceArrays: true);
            
            // Standard response format for both Turnstile and hCaptcha
            return is_array($data) && isset($data['success']) && $data['success'] === true;
        } catch (JsonException $e) {
            throw new \RuntimeException('Invalid JSON response from verification service: ' . $e->getMessage());
        }
    }
}
