<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Transport using an injected PSR-18 client
 *
 * @internal
 */
class Psr18Transport implements Transport
{
    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly ClientInterface $client,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        // Some clients (e.g. Symfony Psr18Client) implement the PSR-17 factories too
        $requestFactory ??= $client instanceof RequestFactoryInterface ? $client : null;
        $streamFactory ??= $client instanceof StreamFactoryInterface ? $client : null;
        if ($requestFactory === null || $streamFactory === null) {
            throw new \InvalidArgumentException('PSR-18 HTTP client requires PSR-17 request and stream factories, pass them to CaptchaValidator');
        }

        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    public function post(string $url, array $data): string
    {
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query($data)));

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new HttpException('Failed to contact verification service: ' . $e->getMessage(), 0, $e);
        }

        // Client errors (e.g. invalid secret) still carry the JSON response with error codes
        if ($response->getStatusCode() >= 500) {
            throw new HttpException('Verification service responded with HTTP ' . $response->getStatusCode());
        }

        return (string) $response->getBody();
    }
}
