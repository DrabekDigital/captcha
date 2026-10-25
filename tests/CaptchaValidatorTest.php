<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests;

use DrabekDigital\Captcha\CaptchaValidator;
use DrabekDigital\Captcha\Enums\CaptchaType;
use DrabekDigital\Captcha\Http\CurlTransport;
use DrabekDigital\Captcha\Tests\Http\FakePsr18Client;
use Nette\Http\IRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;

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
        $reflection = new ReflectionClass($validator);
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
        $reflection = new ReflectionClass($validator);
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
        $reflection = new ReflectionClass($validator);
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
        $reflection = new ReflectionClass($validator);
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
        $reflection = new ReflectionClass($validator);
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
        $reflection = new ReflectionClass($validator);
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
        $reflection = new ReflectionClass($validator);
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
        $reflection = new ReflectionClass($validator);
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
        $reflection = new ReflectionClass($validator);
        $method = $reflection->getMethod('parseResponse');
        
        $responseWithoutSuccess = json_encode(['error' => 'something']);
        $result = $method->invoke($validator, $responseWithoutSuccess);
        self::assertFalse($result);
    }

    /**
     * Create PSR-18 client returning the given body (or throwing) and recording the requests
     *
     * @param string|\Throwable $result
     * @return FakePsr18Client
     */
    private function createHttpClient(string|\Throwable $result): FakePsr18Client
    {
        return new FakePsr18Client(is_string($result) ? new Response(200, [], $result) : $result);
    }

    public function testDefaultTransportIsCurl(): void
    {
        $validator = new CaptchaValidator($this->validSecretKey);

        $property = (new ReflectionClass($validator))->getProperty('transport');

        self::assertInstanceOf(CurlTransport::class, $property->getValue($validator));
    }

    public function testVerifySendsRequestThroughPsr18Client(): void
    {
        $httpRequest = self::createStub(IRequest::class);
        $httpRequest->method('getRemoteAddress')->willReturn('10.0.0.1');
        $client = $this->createHttpClient('{"success":true}');
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::HCAPTCHA, null, $httpRequest, $client);

        self::assertTrue($validator->verify('token'));
        self::assertCount(1, $client->requests);
        self::assertSame('POST', $client->requests[0]->getMethod());
        self::assertSame('https://hcaptcha.com/siteverify', (string) $client->requests[0]->getUri());
        self::assertSame('application/x-www-form-urlencoded', $client->requests[0]->getHeaderLine('Content-Type'));
        self::assertSame(
            'secret=' . $this->validSecretKey . '&response=token&remoteip=10.0.0.1',
            (string) $client->requests[0]->getBody()
        );
    }

    public function testVerifyWithPsr18ClientAndSeparateFactories(): void
    {
        $response = new Response(200, [], '{"success":true}');
        $client = new class($response) implements ClientInterface {
            public function __construct(private ResponseInterface $response)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
        $factory = new Psr17Factory();
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, null, null, $client, $factory, $factory);

        self::assertTrue($validator->verify('token'));
    }

    public function testPsr18ClientWithoutFactoriesThrowsException(): void
    {
        $client = self::createStub(ClientInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PSR-18 HTTP client requires PSR-17 request and stream factories');

        new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, null, null, $client);
    }

    public function testVerifyUsesCustomVerifyUrl(): void
    {
        $client = $this->createHttpClient('{"success":true}');
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, 'https://example.com/verify', null, $client);

        $validator->verify('token');

        self::assertSame('https://example.com/verify', (string) $client->requests[0]->getUri());
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
        $client = $this->createHttpClient(new class('Connection refused') extends \RuntimeException implements ClientExceptionInterface {
        });
        $validator = new CaptchaValidator($this->validSecretKey, CaptchaType::TURNSTILE, null, null, $client);

        self::assertFalse($validator->verify('token'));
    }
}
