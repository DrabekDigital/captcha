<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Assets;

use DrabekDigital\Captcha\Enums\CaptchaType;
use Nette\StaticClass;
use Nette\Utils\FileSystem;
use Nette\Utils\Html;

class CaptchaFrontend
{
    use StaticClass;

    /**
     * Get JavaScript code for loading captcha based on explicit request
     *
     * @param CaptchaType $type
     * @param string|null $nonce CSP nonce added to the script tag
     * @return string
     */
    public static function getJavascriptStatic(CaptchaType $type, ?string $nonce = null): string
    {
        $src = match ($type) {
            CaptchaType::TURNSTILE => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            CaptchaType::HCAPTCHA => 'https://js.hcaptcha.com/1/api.js',
        };

        return (string) Html::el('script', ['src' => $src, 'async' => true, 'defer' => true, 'nonce' => $nonce]);
    }

    /**
     * Get JavaScript code for captcha form validation and managed states labels
     *
     * @param string|null $nonce CSP nonce added to the script tag
     * @return string
     * @throws \Nette\IOException when the bundled script cannot be read
     */
    public static function getLocalJavascriptStatic(?string $nonce = null): string
    {
        return (string) Html::el('script', ['nonce' => $nonce])->setHtml(FileSystem::read(self::getLocalJavascriptPath()));
    }

    /**
     * Get path of the bundled validation script, e.g. to serve it as a static file
     *
     * @return string
     */
    public static function getLocalJavascriptPath(): string
    {
        return __DIR__ . '/captcha-validation.js';
    }
}
