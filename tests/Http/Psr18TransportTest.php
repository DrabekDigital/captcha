<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests\Http;

use DrabekDigital\Captcha\Http\HttpException;
use DrabekDigital\Captcha\Http\Psr18Transport;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

class Psr18TransportTest extends TestCase
{
    public function testPostSendsFormEncodedRequest(): void
    {
        $client = new FakePsr18Client(new Response(200, [], '{"success":true}'));

        $body = (new Psr18Transport($client))->post('https://example.com/verify', ['secret' => 's', 'response' => 'r']);

        self::assertSame('{"success":true}', $body);
        self::assertSame('POST', $client->requests[0]->getMethod());
        self::assertSame('https://example.com/verify', (string) $client->requests[0]->getUri());
        self::assertSame('application/x-www-form-urlencoded', $client->requests[0]->getHeaderLine('Content-Type'));
        self::assertSame('secret=s&response=r', (string) $client->requests[0]->getBody());
    }

    public function testPostReturnsBodyOfClientError(): void
    {
        $client = new FakePsr18Client(new Response(400, [], '{"success":false,"error-codes":["invalid-input-secret"]}'));

        self::assertSame('{"success":false,"error-codes":["invalid-input-secret"]}', (new Psr18Transport($client))->post('https://example.com', []));
    }

    public function testPostThrowsOnServerError(): void
    {
        $client = new FakePsr18Client(new Response(503));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('HTTP 503');

        (new Psr18Transport($client))->post('https://example.com', []);
    }

    public function testPostThrowsOnClientException(): void
    {
        $client = new FakePsr18Client(new class('Connection refused') extends \RuntimeException implements ClientExceptionInterface {
        });

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Failed to contact verification service: Connection refused');

        (new Psr18Transport($client))->post('https://example.com', []);
    }
}
