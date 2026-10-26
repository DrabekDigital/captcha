<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests\Latte;

use DrabekDigital\Captcha\Assets\CaptchaFrontend;
use DrabekDigital\Captcha\DI\CaptchaExtension;
use DrabekDigital\Captcha\Enums\CaptchaType;
use DrabekDigital\Captcha\Latte\CaptchaLatteExtension;
use Latte\Engine;
use Latte\Loaders\StringLoader;
use Nette\Bridges\ApplicationDI\LatteExtension;
use Nette\Bridges\ApplicationLatte\LatteFactory;
use Nette\DI\Compiler;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;

class CaptchaLatteExtensionTest extends TestCase
{
    private function createLatte(CaptchaType $type): Engine
    {
        $latte = new Engine();
        $latte->setLoader(new StringLoader());
        $latte->addExtension(new CaptchaLatteExtension($type));
        return $latte;
    }

    public function testCaptchaScriptsTag(): void
    {
        $html = $this->createLatte(CaptchaType::HCAPTCHA)->renderToString('{captchaScripts}');

        self::assertSame(
            CaptchaFrontend::getJavascriptStatic(CaptchaType::HCAPTCHA) . "\n" . CaptchaFrontend::getLocalJavascriptStatic(),
            $html
        );
    }

    public function testCaptchaScriptsTagUsesNonce(): void
    {
        $latte = $this->createLatte(CaptchaType::TURNSTILE);
        $latte->addProvider('uiNonce', 'abc123');

        $html = $latte->renderToString('{captchaScripts}');

        self::assertStringStartsWith('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="abc123"></script>', $html);
        self::assertStringContainsString('<script nonce="abc123">', $html);
    }

    public function testExtensionIsRegisteredToLatteFactory(): void
    {
        $compiler = new Compiler();
        $compiler->addExtension('latte', new LatteExtension(sys_get_temp_dir()));
        $compiler->addExtension('captcha', new CaptchaExtension());
        $compiler->addConfig(['captcha' => ['type' => 'hcaptcha', 'secretKey' => 'secret', 'siteKey' => 'site']]);
        $containerClassName = 'TestLatteContainer' . uniqid();
        $compiler->setClassName($containerClassName);
        eval($compiler->compile()); // @phpstan-ignore-line

        /** @var Container $container */
        $container = new $containerClassName();
        $latte = $container->getByType(LatteFactory::class)->create();
        $latte->setLoader(new StringLoader());

        self::assertStringStartsWith('<script src="https://js.hcaptcha.com/1/api.js"', $latte->renderToString('{captchaScripts}'));
    }
}
