<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * PSR-18 client implementing PSR-17 factories too (like Symfony Psr18Client), records sent requests
 */
class FakePsr18Client implements ClientInterface, RequestFactoryInterface, StreamFactoryInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    private readonly Psr17Factory $factory;

    public function __construct(
        private readonly ResponseInterface|\Throwable $result = new Response(200, [], '{"success":true}')
    ) {
        $this->factory = new Psr17Factory();
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }
        return $this->result;
    }

    public function createRequest(string $method, $uri): RequestInterface
    {
        return $this->factory->createRequest($method, $uri);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return $this->factory->createStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return $this->factory->createStreamFromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return $this->factory->createStreamFromResource($resource);
    }
}
