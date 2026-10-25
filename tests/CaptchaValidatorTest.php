<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests;

use DrabekDigital\Captcha\CaptchaValidator;
use DrabekDigital\Captcha\Enums\CaptchaType;
use DrabekDigital\Captcha\Http\CurlHttpClient;
use DrabekDigital\Captcha\Http\HttpClient;
use DrabekDigital\Captcha\Http\HttpException;
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
        $httpRequest = self::createStub(IRequest::class);
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
        $httpRequest = self::createStub(IRequest::class);
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
        $httpRequest = self::createStub(IRequest::class);
        $httpRequest->method('getRemoteAddress')->willReturn('10.0.0.1');
        
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, null, $httpRequest);
        
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($validator);
        $method = $reflection->getMethod('buildPostData');
        
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
        
        $responseWithoutSuccess = json_encode(['error' => 'something']);
        $result = $method->invoke($validator, $responseWithoutSuccess);
        self::assertFalse($result);
    }

    /**
     * Create HTTP client returning the given body and recording the requests
     *
     * @param string|\Throwable $result
     * @return HttpClient&object{requests: list<array{string, array<string, string>}>}
     */
    private function createHttpClient(string|\Throwable $result): HttpClient
    {
        return new class($result) implements HttpClient {
            /** @var list<array{string, array<string, string>}> */
            public array $requests = [];

            public function __construct(private string|\Throwable $result)
            {
            }

            public function post(string $url, array $data): string
            {
                $this->requests[] = [$url, $data];
                if ($this->result instanceof \Throwable) {
                    throw $this->result;
                }
                return $this->result;
            }
        };
    }

    public function testDefaultHttpClientIsCurl(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey);

        $property = (new \ReflectionClass($validator))->getProperty('httpClient');

        self::assertInstanceOf(CurlHttpClient::class, $property->getValue($validator));
    }

    public function testVerifySendsRequestToVerificationService(): void
    {
        $httpRequest = self::createStub(IRequest::class);
        $httpRequest->method('getRemoteAddress')->willReturn('10.0.0.1');
        $client = $this->createHttpClient('{"success":true}');
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::HCAPTCHA, null, $httpRequest, $client);

        self::assertTrue($validator->verify('token'));
        self::assertSame([
            ['https://hcaptcha.com/siteverify', ['secret' => $this->validSecretKey, 'response' => 'token', 'remoteip' => '10.0.0.1']],
        ], $client->requests);
    }

    public function testVerifyUsesCustomVerifyUrl(): void
    {
        $client = $this->createHttpClient('{"success":true}');
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, 'https://example.com/verify', null, $client);

        $validator->verify('token');

        self::assertSame('https://example.com/verify', $client->requests[0][0]);
    }

    public function testVerifyWithUnsuccessfulResponse(): void
    {
        $client = $this->createHttpClient('{"success":false,"error-codes":["invalid-input-response"]}');
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, null, null, $client);

        self::assertFalse($validator->verify('token'));
    }

    public function testVerifyWithInvalidJsonResponse(): void
    {
        $client = $this->createHttpClient('<html>Bad gateway</html>');
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, null, null, $client);

        self::assertFalse($validator->verify('token'));
    }

    public function testVerifyWhenServiceIsUnavailable(): void
    {
        $client = $this->createHttpClient(new HttpException('Failed to contact verification service'));
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, null, null, $client);

        self::assertFalse($validator->verify('token'));
    }
}
