<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests;

use DrabekDigital\Captcha\Assets\CaptchaFrontend;
use DrabekDigital\Captcha\Enums\CaptchaType;
use PHPUnit\Framework\TestCase;

class CaptchaFrontendTest extends TestCase
{
    public function testGetJavascriptStaticForTurnstile(): void
    {
        self::assertSame(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>',
            CaptchaFrontend::getJavascriptStatic(CaptchaType::TURNSTILE)
        );
    }

    public function testGetJavascriptStaticForHcaptcha(): void
    {
        self::assertSame(
            '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>',
            CaptchaFrontend::getJavascriptStatic(CaptchaType::HCAPTCHA)
        );
    }

    public function testGetLocalJavascriptStaticInlinesValidationScript(): void
    {
        $script = file_get_contents(__DIR__ . '/../src/Assets/captcha-validation.js');

        self::assertSame('<script>' . $script . '</script>', CaptchaFrontend::getLocalJavascriptStatic());
    }

    public function testGetJavascriptStaticWithNonce(): void
    {
        self::assertSame(
            '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce=\'abc"123\'></script>',
            CaptchaFrontend::getJavascriptStatic(CaptchaType::TURNSTILE, 'abc"123')
        );
    }

    public function testGetLocalJavascriptStaticWithNonce(): void
    {
        $script = file_get_contents(CaptchaFrontend::getLocalJavascriptPath());

        self::assertSame('<script nonce="abc123">' . $script . '</script>', CaptchaFrontend::getLocalJavascriptStatic('abc123'));
    }
}
