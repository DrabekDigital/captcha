<?php

declare(strict_types=1);

namespace DrabekDigital\Captcha\Tests\Http;

use DrabekDigital\Captcha\Http\CurlHttpClient;
use DrabekDigital\Captcha\Http\HttpClient;
use DrabekDigital\Captcha\Http\HttpException;
use DrabekDigital\Captcha\Http\StreamHttpClient;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;

    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        // Reserve a free port for the built-in server
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            self::markTestSkipped('Cannot open a local socket');
        }
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        self::$baseUrl = 'http://' . $address;
        // Local test server only, the command is fixed
        $process = proc_open( // @phpstan-ignore disallowed.function
            [PHP_BINARY, '-S', $address, __DIR__ . '/server.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        self::$server = $process === false ? null : $process;

        // Wait until the server accepts connections
        for ($i = 0; $i < 50; $i++) {
            $connection = @fsockopen('127.0.0.1', (int) parse_url(self::$baseUrl, PHP_URL_PORT));
            if ($connection !== false) {
                fclose($connection);
                return;
            }
            usleep(100_000);
        }
        self::markTestSkipped('Built-in PHP server did not start');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

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
    public function testPostSendsFormEncodedData(HttpClient $client): void
    {
        $response = Json::decode($client->post(self::$baseUrl . '/echo', ['secret' => 's', 'response' => 'r']), forceArrays: true);

        self::assertSame([
            'method' => 'POST',
            'contentType' => 'application/x-www-form-urlencoded',
            'post' => ['secret' => 's', 'response' => 'r'],
        ], $response);
    }

    #[DataProvider('clientsProvider')]
    public function testPostReturnsBodyOfClientError(HttpClient $client): void
    {
        self::assertSame(
            '{"success":false,"error-codes":["invalid-input-secret"]}',
            $client->post(self::$baseUrl . '/client-error', [])
        );
    }

    public function testCurlThrowsOnServerError(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('HTTP 500');

        (new CurlHttpClient())->post(self::$baseUrl . '/server-error', []);
    }

    #[DataProvider('clientsProvider')]
    public function testPostThrowsWhenServiceIsUnreachable(HttpClient $client): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Failed to contact verification service');

        // Port 9 (discard) on localhost is not listening
        $client->post('http://127.0.0.1:9/', []);
    }
}
