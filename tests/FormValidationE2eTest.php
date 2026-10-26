<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests;

use Closure;
use DrabekDigital\Captcha\CaptchaControl;
use DrabekDigital\Captcha\DI\CaptchaExtension;
use DrabekDigital\Captcha\Tests\Support\MemoryLogger;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\Forms\Form;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Submits forms built by the DI extension and verifies tokens against the real services
 * with their testing keys, run by `composer e2e`
 *
 * @see https://developers.cloudflare.com/turnstile/troubleshooting/testing/
 * @see https://docs.hcaptcha.com/#integration-testing-test-keys
 */
#[Group('e2e')]
class FormValidationE2eTest extends TestCase
{
    private const TURNSTILE_SITE_KEY = '1x00000000000000000000AA';
    /** Accepts any token */
    private const TURNSTILE_PASS_SECRET = '1x0000000000000000000000000000000AA';
    /** Rejects any token */
    private const TURNSTILE_FAIL_SECRET = '2x0000000000000000000000000000000AA';
    private const TURNSTILE_TOKEN = 'XXXX.DUMMY.TOKEN.XXXX';
    private const HCAPTCHA_SITE_KEY = '10000000-ffff-ffff-ffff-000000000001';
    /** Accepts only the testing token */
    private const HCAPTCHA_SECRET = '0x0000000000000000000000000000000000000000';
    private const HCAPTCHA_TOKEN = '10000000-aaaa-bbbb-cccc-000000000001';

    private const RESPONSE_FIELDS = [
        'turnstile' => 'cf-turnstile-response',
        'hcaptcha' => 'h-captcha-response',
    ];

    /**
     * Compile container with the captcha extension, it registers addCaptcha() for new forms
     *
     * @param array<string, mixed> $captcha
     * @return Container
     */
    private function createContainer(array $captcha): Container
    {
        $compiler = new Compiler();
        $compiler->addExtension('captcha', new CaptchaExtension());
        $compiler->addConfig(['captcha' => $captcha, 'services' => [MemoryLogger::class]]);
        $className = 'FormValidationE2eContainer' . uniqid();
        $compiler->setClassName($className);
        eval($compiler->compile()); // @phpstan-ignore-line

        /** @var Container $container */
        $container = new $className();
        $container->initialize();
        return $container;
    }

    /**
     * Submit form with the captcha the way a browser does and return its errors
     *
     * @param Closure(CaptchaControl): mixed $configure
     * @param array<string, string> $post
     * @return list<string>|null null when the form is valid
     */
    private function submit(Closure $configure, array $post): ?array
    {
        $form = new Form('form');
        // Set before adding controls, they load submitted values when attached; same-origin request is required
        // by the Nette forms CSRF protection (header for current nette/http, cookie for older versions)
        $form->httpRequest = new Request(
            new UrlScript('http://localhost/'),
            post: $post + [Form::TrackerId => 'form', 'send' => 'Send'],
            cookies: ['_nss' => '1'],
            headers: ['Sec-Fetch-Site' => 'same-origin'],
            method: 'POST',
        );
        /** @var CaptchaControl $captcha */
        $captcha = $form->addCaptcha('captcha', 'Captcha');
        $configure($captcha);
        $form->addSubmit('send', 'Send');
        $form->onSuccess[] = fn () => null;
        $form->fireEvents();

        self::assertNotFalse($form->isSubmitted(), 'Form was not submitted');
        return $form->isSuccess() ? null : array_map('strval', $form->getErrors());
    }

    /**
     * @return array<string, array{string, Closure(CaptchaControl): mixed}>
     */
    public static function modesProvider(): array
    {
        return [
            'turnstile' => ['turnstile', fn (CaptchaControl $control) => $control],
            'turnstile invisible' => ['turnstile', fn (CaptchaControl $control) => $control->setInvisible()],
            'turnstile managed' => ['turnstile', fn (CaptchaControl $control) => $control->setManagedMessages('Pending', 'Resolved')],
            'hcaptcha' => ['hcaptcha', fn (CaptchaControl $control) => $control],
            'hcaptcha invisible' => ['hcaptcha', fn (CaptchaControl $control) => $control->setInvisible()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(string $type, bool $passingSecret = true): array
    {
        return $type === 'turnstile'
            ? ['type' => $type, 'siteKey' => self::TURNSTILE_SITE_KEY, 'secretKey' => $passingSecret ? self::TURNSTILE_PASS_SECRET : self::TURNSTILE_FAIL_SECRET]
            : ['type' => $type, 'siteKey' => self::HCAPTCHA_SITE_KEY, 'secretKey' => self::HCAPTCHA_SECRET];
    }

    /**
     * @param Closure(CaptchaControl): mixed $configure
     */
    #[DataProvider('modesProvider')]
    public function testFormWithoutTokenIsInvalid(string $type, Closure $configure): void
    {
        $this->createContainer(self::config($type));

        self::assertSame(['Please verify you are human.'], $this->submit($configure, []));
    }

    /**
     * @param Closure(CaptchaControl): mixed $configure
     */
    #[DataProvider('modesProvider')]
    public function testFormWithValidTokenIsValid(string $type, Closure $configure): void
    {
        $this->createContainer(self::config($type));
        $token = $type === 'turnstile' ? self::TURNSTILE_TOKEN : self::HCAPTCHA_TOKEN;

        self::assertNull($this->submit($configure, [self::RESPONSE_FIELDS[$type] => $token]));
    }

    /**
     * @param Closure(CaptchaControl): mixed $configure
     */
    #[DataProvider('modesProvider')]
    public function testFormWithRejectedTokenIsInvalid(string $type, Closure $configure): void
    {
        // Turnstile passing testing secret accepts any token, the failing one rejects it
        $this->createContainer(self::config($type, passingSecret: false));

        self::assertNotNull($this->submit($configure, [self::RESPONSE_FIELDS[$type] => 'invalid-token']));
    }

    public function testTokenOfOtherProviderIsIgnored(): void
    {
        $this->createContainer(self::config('turnstile'));

        self::assertNotNull($this->submit(fn (CaptchaControl $control) => $control, ['h-captcha-response' => self::HCAPTCHA_TOKEN]));
    }

    public function testInvalidSecretIsLoggedAsMisconfiguration(): void
    {
        $container = $this->createContainer(['secretKey' => 'invalid-secret'] + self::config('turnstile'));

        self::assertNotNull($this->submit(fn (CaptchaControl $control) => $control, ['cf-turnstile-response' => self::TURNSTILE_TOKEN]));
        self::assertSame(['error: Captcha verification is misconfigured: invalid-input-secret'], $container->getByType(MemoryLogger::class)->records);
    }

    public function testRejectedTokenIsLoggedAsInfo(): void
    {
        $container = $this->createContainer(self::config('turnstile', passingSecret: false));

        self::assertNotNull($this->submit(fn (CaptchaControl $control) => $control, ['cf-turnstile-response' => self::TURNSTILE_TOKEN]));
        self::assertSame(['info: Captcha token was rejected: invalid-input-response'], $container->getByType(MemoryLogger::class)->records);
    }

    public function testUnavailableServiceIsLoggedAsError(): void
    {
        // Port 9 (discard) on localhost is not listening
        $container = $this->createContainer(['verifyUrl' => 'http://127.0.0.1:9/'] + self::config('turnstile'));

        self::assertNotNull($this->submit(fn (CaptchaControl $control) => $control, ['cf-turnstile-response' => self::TURNSTILE_TOKEN]));
        $records = $container->getByType(MemoryLogger::class)->records;
        self::assertCount(1, $records);
        self::assertStringStartsWith('error: Captcha verification service is unavailable:', $records[0]);
    }
}
