<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Assets;

use DrabekDigital\Captcha\Enums\CaptchaType;
use Nette\StaticClass;

class CaptchaFrontend
{
    use StaticClass;

    /**
     * Get JavaScript code for loading captcha based on explicit request
     *
     * @param CaptchaType $type
     * @return string
     */
    public static function getJavascriptStatic(CaptchaType $type): string
    {
        return match ($type) {
            CaptchaType::TURNSTILE => '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>',
            CaptchaType::HCAPTCHA => '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>',
        };
    }

    /**
     * Get JavaScript code for captcha form validation and managed states labels
     * @return string
     */
    public static function getLocalJavascriptStatic(): string
    {
        return "<script>" . file_get_contents(__DIR__ . '/captcha-validation.js') . "</script>";
    }
}
