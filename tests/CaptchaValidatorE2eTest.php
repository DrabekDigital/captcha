<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests;

use DrabekDigital\Captcha\CaptchaValidator;
use DrabekDigital\Captcha\Enums\CaptchaType;
use DrabekDigital\Captcha\Http\CurlHttpClient;
use DrabekDigital\Captcha\Http\HttpClient;
use DrabekDigital\Captcha\Http\StreamHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Calls the real verification services with their testing keys, run by `composer e2e`
 *
 * @see https://developers.cloudflare.com/turnstile/troubleshooting/testing/
 * @see https://docs.hcaptcha.com/#integration-testing-test-keys
 */
#[Group('e2e')]
class CaptchaValidatorE2eTest extends TestCase
{
    private const TURNSTILE_PASS_SECRET = '1x0000000000000000000000000000000AA';
    private const TURNSTILE_FAIL_SECRET = '2x0000000000000000000000000000000AA';
    private const TURNSTILE_DUMMY_TOKEN = 'XXXX.DUMMY.TOKEN.XXXX';
    private const HCAPTCHA_SECRET = '0x0000000000000000000000000000000000000000';
    private const HCAPTCHA_PASS_TOKEN = '10000000-aaaa-bbbb-cccc-000000000001';

    /**
     * @return array<string, array{HttpClient}>
     */
    public static function clientsProvider(): array
    {
        return [
            'curl' => [new CurlHttpClient()],
            'stream' => [new StreamHttpClient()],
        ];
    }

    #[DataProvider('clientsProvider')]
    public function testTurnstilePasses(HttpClient $client): void
    {
        $validator = new CaptchaValidator(self::TURNSTILE_PASS_SECRET, CaptchaType::TURNSTILE, null, null, $client);

        self::assertTrue($validator->verify(self::TURNSTILE_DUMMY_TOKEN));
    }

    #[DataProvider('clientsProvider')]
    public function testTurnstileFails(HttpClient $client): void
    {
        $validator = new CaptchaValidator(self::TURNSTILE_FAIL_SECRET, CaptchaType::TURNSTILE, null, null, $client);

        self::assertFalse($validator->verify(self::TURNSTILE_DUMMY_TOKEN));
    }

    #[DataProvider('clientsProvider')]
    public function testHcaptchaPasses(HttpClient $client): void
    {
        $validator = new CaptchaValidator(self::HCAPTCHA_SECRET, CaptchaType::HCAPTCHA, null, null, $client);

        self::assertTrue($validator->verify(self::HCAPTCHA_PASS_TOKEN));
    }

    #[DataProvider('clientsProvider')]
    public function testHcaptchaFails(HttpClient $client): void
    {
        $validator = new CaptchaValidator(self::HCAPTCHA_SECRET, CaptchaType::HCAPTCHA, null, null, $client);

        self::assertFalse($validator->verify('invalid-token'));
    }
}
