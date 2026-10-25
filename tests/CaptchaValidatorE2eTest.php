<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests;

use DrabekDigital\Captcha\CaptchaValidator;
use DrabekDigital\Captcha\Enums\CaptchaType;
use DrabekDigital\Captcha\Http\CurlTransport;
use DrabekDigital\Captcha\Http\StreamTransport;
use DrabekDigital\Captcha\Http\Transport;
use Nette\Utils\Json;
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

    public function testTurnstilePasses(): void
    {
        $validator = new CaptchaValidator(self::TURNSTILE_PASS_SECRET, CaptchaType::TURNSTILE);

        self::assertTrue($validator->verify(self::TURNSTILE_DUMMY_TOKEN));
    }

    public function testTurnstileFails(): void
    {
        $validator = new CaptchaValidator(self::TURNSTILE_FAIL_SECRET, CaptchaType::TURNSTILE);

        self::assertFalse($validator->verify(self::TURNSTILE_DUMMY_TOKEN));
    }

    public function testHcaptchaPasses(): void
    {
        $validator = new CaptchaValidator(self::HCAPTCHA_SECRET, CaptchaType::HCAPTCHA);

        self::assertTrue($validator->verify(self::HCAPTCHA_PASS_TOKEN));
    }

    public function testHcaptchaFails(): void
    {
        $validator = new CaptchaValidator(self::HCAPTCHA_SECRET, CaptchaType::HCAPTCHA);

        self::assertFalse($validator->verify('invalid-token'));
    }

    /**
     * @return array<string, array{Transport}>
     */
    public static function transportsProvider(): array
    {
        return [
            'curl' => [new CurlTransport()],
            'stream' => [new StreamTransport()],
        ];
    }

    #[DataProvider('transportsProvider')]
    public function testTransportReachesVerificationService(Transport $transport): void
    {
        $response = $transport->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => self::TURNSTILE_PASS_SECRET,
            'response' => self::TURNSTILE_DUMMY_TOKEN,
        ]);

        $data = Json::decode($response, forceArrays: true);
        self::assertIsArray($data);
        self::assertTrue($data['success'] ?? false);
    }
}
