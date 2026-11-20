<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Support;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKitBundle\Tests\Application\Kernel;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * castor-api for the tests, as the response factory of a MockHttpClient. The list tasks return the JSON of
 * Fixtures/castor-api/, the other tasks succeed with no output unless fail() was called. Every call is recorded.
 *
 * The state is static: WebTestCase reboots the kernel, hence this service, between two requests.
 */
final class FakeCastorApi
{
    private const OUTPUTS = [
        'sylius:shop:list' => 'shops.json',
        'sylius:dataset:list' => 'datasets.json',
        'sales-kit:theme:list' => 'themes.json',
    ];

    /** @var list<array{method: string, task: string|null, action: string, payload: array<string, mixed>, token: string|null}> */
    private static array $calls = [];

    /** @var array<string, string> task => error output */
    private static array $failures = [];

    /** @var array<string, array{task: string, payload: array<string, mixed>}> task => run that holds the lock */
    private static array $conflicts = [];

    /** @var array<string, string> task => stdout */
    private static array $outputs = [];

    /** A client of this fake castor-api, for the tests that do not boot the kernel. */
    public static function client(?string $token = 'secret-token'): CastorApiClient
    {
        return new CastorApiClient(new MockHttpClient(new self(), 'http://castor-api.test'), 'http://castor-api.test', $token);
    }

    public static function reset(): void
    {
        self::$calls = [];
        self::$failures = [];
        self::$outputs = [];
        self::$conflicts = [];
    }

    /**
     * Starting the task answers HTTP 409: this other run holds its lock.
     *
     * @param array<string, mixed> $payload
     */
    public static function conflict(string $task, string $runningTask, array $payload): void
    {
        self::$conflicts[$task] = ['task' => $runningTask, 'payload' => $payload];
    }

    /** The task fails with this output (HTTP 422, like castor-api for a failed Castor task). */
    public static function fail(string $task, string $output): void
    {
        self::$failures[$task] = $output;
    }

    /** The task prints this instead of its fixture. */
    public static function output(string $task, string $stdout): void
    {
        self::$outputs[$task] = $stdout;
    }

    /**
     * @return list<array{method: string, task: string|null, action: string, payload: array<string, mixed>, token: string|null}>
     */
    public static function calls(?string $task = null): array
    {
        return array_values(array_filter(self::$calls, static fn(array $call): bool => null === $task || $call['task'] === $task));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $path = (string) parse_url($url, \PHP_URL_PATH);
        $payload = \is_string($options['body'] ?? null) && '' !== $options['body'] ? json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR) : [];
        $token = null;

        foreach ($options['headers'] ?? [] as $header) {
            if (str_starts_with(strtolower((string) $header), 'authorization: bearer ')) {
                $token = substr((string) $header, \strlen('authorization: bearer '));
            }
        }

        if ('/runs' === $path) {
            self::$calls[] = ['method' => $method, 'task' => null, 'action' => 'runs', 'payload' => [], 'token' => $token];

            return self::json(['runs' => []]);
        }

        if (!preg_match('#^/tasks/([^/]+)/(run|start|status)(?:/([^/]+))?$#', $path, $match)) {
            return self::json(['error' => 'Not found.'], 404);
        }

        $task = rawurldecode($match[1]);
        self::$calls[] = ['method' => $method, 'task' => $task, 'action' => $match[2], 'payload' => \is_array($payload) ? $payload : [], 'token' => $token];

        if (isset(self::$conflicts[$task])) {
            return self::json(['error' => 'Another task is running.', 'conflict' => self::$conflicts[$task]], 409);
        }

        if (isset(self::$failures[$task])) {
            return self::json(['status' => 'failed', 'exitCode' => 1, 'stdout' => '', 'stderr' => self::$failures[$task]], 422);
        }

        return match ($match[2]) {
            'start' => self::json(['id' => 'run-1', 'task' => $task, 'status' => 'pending']),
            'status' => self::json(['id' => $match[3] ?? '', 'task' => $task, 'status' => 'succeeded']),
            default => self::json(['status' => 'succeeded', 'exitCode' => 0, 'stdout' => self::stdout($task), 'stderr' => '']),
        };
    }

    private static function stdout(string $task): string
    {
        if (isset(self::$outputs[$task])) {
            return self::$outputs[$task];
        }

        return isset(self::OUTPUTS[$task]) ? (string) file_get_contents(Kernel::FIXTURES_DIR . '/castor-api/' . self::OUTPUTS[$task]) : '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function json(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data, \JSON_THROW_ON_ERROR), ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']]);
    }
}
