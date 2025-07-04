<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha;

use DrabekDigital\Captcha\Enums\CaptchaType;
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

    public function __construct(
        private readonly string $secretKey,
        private readonly CaptchaType $type = CaptchaType::TURNSTILE,
        private readonly ?string $verifyUrl = null,
        private readonly ?IRequest $httpRequest = null
    ) {
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
            $result = $this->makeHttpRequest($verifyUrl, $postData);
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
     * @return array<mixed>
     */
    private function buildPostData(string $response, ?string $remoteIp = null): array
    {
        $data = [
            'secret' => $this->secretKey,
            'response' => $response,
        ];

        // Add remote IP if available
        if ($remoteIp !== null && $remoteIp !== '') {
            $data['remoteip'] = $remoteIp;
        } elseif ($this->httpRequest !== null) {
            $data['remoteip'] = $this->httpRequest->getRemoteAddress();
        }

        return $data;
    }

    /**
     * Make HTTP request to verification endpoint
     *
     * @param string $url
     * @param array<mixed> $postData
     * @return string
     */
    private function makeHttpRequest(string $url, array $postData): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($postData),
                'timeout' => 10,
            ],
        ]);

        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            throw new \RuntimeException('Failed to contact verification service');
        }

        return $result;
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
            $data = Json::decode($response, Json::FORCE_ARRAY);
            
            // Standard response format for both Turnstile and hCaptcha
            return is_array($data) && isset($data['success']) && $data['success'] === true;
        } catch (JsonException $e) {
            throw new \RuntimeException('Invalid JSON response from verification service: ' . $e->getMessage());
        }
    }
}
