<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests;

use DrabekDigital\Captcha\CaptchaValidator;
use DrabekDigital\Captcha\Enums\CaptchaType;
use Nette\Http\IRequest;
use PHPUnit\Framework\TestCase;

class CaptchaValidatorTest extends TestCase
{
    private string $validSecretKey = 'test-secret-key';

    public function testConstructorWithDefaults(): void
    {
        $validator = new CaptchaValidator('secret-key');
        self::assertInstanceOf(CaptchaValidator::class, $validator); // @phpstan-ignore-line
    }

    public function testConstructorWithAllParameters(): void
    {
        $httpRequest = $this->createMock(IRequest::class);
        $validator = new CaptchaValidator(
            'secret-key',
            CaptchaType::HCAPTCHA,
            'https://custom-verify-url.com',
            $httpRequest
        );
        self::assertInstanceOf(CaptchaValidator::class, $validator); // @phpstan-ignore-line
    }

    public function testVerifyWithEmptyResponse(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey);
        $result = $validator->verify('');
        self::assertFalse($result);
    }

    public function testVerifyWithMockedHttpRequest(): void
    {
        // Create a mock HTTP request
        $httpRequest = $this->createMock(IRequest::class);
        $httpRequest->method('getRemoteAddress')->willReturn('127.0.0.1');

        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, null, $httpRequest);
        
        // Only test with empty response to avoid network calls
        $result = $validator->verify('');
        self::assertFalse($result);
    }

    public function testVerifyWithCustomVerifyUrl(): void
    {
        $validator = new CaptchaValidator(
            $this->validSecretKey,
            CaptchaType::TURNSTILE,
            'https://custom-verify-url.com'
        );
        
        // Only test with empty response to avoid network calls
        $result = $validator->verify('');
        self::assertFalse($result);
    }

    public function testVerifyWithRemoteIp(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey);
        
        // Only test with empty response to avoid network calls
        $result = $validator->verify('', '192.168.1.1');
        self::assertFalse($result);
    }

    /**
     * Test that verifyUrl is properly determined for turnstile
     */
    public function testGetVerifyUrlForTurnstile(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('getVerifyUrl');
        $method->setAccessible(true);
        
        $result = $method->invoke($validator);
        self::assertEquals('https://challenges.cloudflare.com/turnstile/v0/siteverify', $result);
    }

    /**
     * Test that verifyUrl is properly determined for hcaptcha
     */
    public function testGetVerifyUrlForHcaptcha(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::HCAPTCHA);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('getVerifyUrl');
        $method->setAccessible(true);
        
        $result = $method->invoke($validator);
        self::assertEquals('https://hcaptcha.com/siteverify', $result);
    }

    /**
     * Test that custom verifyUrl is returned when set
     */
    public function testGetVerifyUrlWithCustomUrl(): void
    {
        $customUrl = 'https://example.com/verify';
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, $customUrl);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('getVerifyUrl');
        $method->setAccessible(true);
        
        $result = $method->invoke($validator);
        self::assertEquals($customUrl, $result);
    }

    /**
     * Test buildPostData method
     */
    public function testBuildPostData(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('buildPostData');
        $method->setAccessible(true);
        
        $result = $method->invoke($validator, 'test-response');
        
        self::assertIsArray($result);
        self::assertEquals($this->validSecretKey, $result['secret']);
        self::assertEquals('test-response', $result['response']);
    }

    /**
     * Test buildPostData method with remote IP
     */
    public function testBuildPostDataWithRemoteIp(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('buildPostData');
        $method->setAccessible(true);
        
        $result = $method->invoke($validator, 'test-response', '192.168.1.1');
        
        self::assertIsArray($result);
        self::assertEquals($this->validSecretKey, $result['secret']);
        self::assertEquals('test-response', $result['response']);
        self::assertEquals('192.168.1.1', $result['remoteip']);
    }

    /**
     * Test buildPostData method with HTTP request
     */
    public function testBuildPostDataWithHttpRequest(): void
    {
        $httpRequest = $this->createMock(IRequest::class);
        $httpRequest->method('getRemoteAddress')->willReturn('10.0.0.1');
        
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, null, $httpRequest);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('buildPostData');
        $method->setAccessible(true);
        
        $result = $method->invoke($validator, 'test-response');
        
        self::assertIsArray($result);
        self::assertEquals($this->validSecretKey, $result['secret']);
        self::assertEquals('test-response', $result['response']);
        self::assertEquals('10.0.0.1', $result['remoteip']);
    }

    /**
     * Test parseResponse method with valid JSON
     */
    public function testParseResponseWithValidJson(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('parseResponse');
        $method->setAccessible(true);
        
        $validResponse = json_encode(['success' => true]);
        $result = $method->invoke($validator, $validResponse);
        self::assertTrue($result);
        
        $invalidResponse = json_encode(['success' => false]);
        $result = $method->invoke($validator, $invalidResponse);
        self::assertFalse($result);
    }

    /**
     * Test parseResponse method with invalid JSON
     */
    public function testParseResponseWithInvalidJson(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('parseResponse');
        $method->setAccessible(true);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON response from verification service');
        
        $method->invoke($validator, 'invalid-json');
    }

    /**
     * Test parseResponse method with missing success field
     */
    public function testParseResponseWithMissingSuccessField(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('parseResponse');
        $method->setAccessible(true);
        
        $responseWithoutSuccess = json_encode(['error' => 'something']);
        $result = $method->invoke($validator, $responseWithoutSuccess);
        self::assertFalse($result);
    }
}
