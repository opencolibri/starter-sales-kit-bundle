<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Castor;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CastorApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        /** Null or empty: castor-api runs without authentication (%env(default::CASTOR_API_TOKEN)%). */
        private readonly ?string $token,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function run(string $task, array $payload = []): array
    {
        $data = $this->request('POST', self::taskPath($task, '/run'), $payload);

        if (self::isFailedTaskPayload($data)) {
            throw new \RuntimeException(self::taskFailureMessage($data));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{id: string, task: string, status: string}
     */
    public function start(string $task, array $payload = []): array
    {
        $data = $this->request('POST', self::taskPath($task, '/start'), $payload);
        $id = (string) ($data['id'] ?? '');

        if ('' === $id) {
            throw new \RuntimeException('castor-api did not return a run id.');
        }

        return [
            'id' => $id,
            'task' => (string) ($data['task'] ?? $task),
            'status' => (string) ($data['status'] ?? 'pending'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $task, string $runId): array
    {
        return $this->request('GET', self::taskPath($task, '/status/' . rawurlencode($runId)));
    }

    /**
     * The async runs that are pending or running, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveRuns(): array
    {
        $runs = $this->request('GET', '/runs')['runs'] ?? [];

        return \is_array($runs) ? array_values(array_filter($runs, \is_array(...))) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listShops(): array
    {
        $result = $this->run('sylius:shop:list');
        $decoded = self::decodeJsonOutput($result);

        return \is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listDatasets(): array
    {
        $result = $this->run('sylius:dataset:list');
        $decoded = self::decodeJsonOutput($result);

        return \is_array($decoded) ? array_values(array_filter($decoded, \is_array(...))) : [];
    }

    /**
     * The storefront themes a shop can pick ("default" for the Sylius look).
     *
     * @return list<string>
     */
    public function listThemes(): array
    {
        return $this->themeCatalog()['themes'];
    }

    /**
     * The storefront themes a shop can pick, and the header layout of each installed theme: where it shows the menu
     * and the search, and the placements it supports. A theme installed before the header layout existed has none.
     *
     * @return array{themes: list<string>, headers: array<string, array{menu: string, search: string, menus: list<string>, searches: list<string>}>}
     */
    public function themeCatalog(): array
    {
        $result = $this->run('sales-kit:theme:list');
        $decoded = self::decodeJsonOutput($result);

        if (!\is_array($decoded)) {
            throw new \RuntimeException('castor-api returned an invalid theme list.');
        }

        $headers = [];

        foreach (\is_array($decoded['headers'] ?? null) ? $decoded['headers'] : [] as $theme => $header) {
            if (\is_string($theme) && \is_array($header) && \is_string($header['menu'] ?? null) && \is_string($header['search'] ?? null)) {
                $headers[$theme] = [
                    'menu' => $header['menu'],
                    'search' => $header['search'],
                    'menus' => self::strings($header['menus'] ?? null),
                    'searches' => self::strings($header['searches'] ?? null),
                ];
            }
        }

        return [
            'themes' => self::strings($decoded['themes'] ?? null),
            'headers' => $headers,
        ];
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return array_values(array_filter(\is_array($values) ? $values : [], \is_string(...)));
    }

    /**
     * Castor may print notices (e.g. "A new Castor version is available") before the JSON output.
     *
     * @param array<string, mixed> $result
     */
    private static function decodeJsonOutput(array $result): mixed
    {
        $stdout = trim((string) ($result['stdout'] ?? ''));
        if (!preg_match('/^[\[{]/m', $stdout, $match, \PREG_OFFSET_CAPTURE)) {
            return null;
        }

        try {
            return json_decode(substr($stdout, $match[0][1]), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private static function taskPath(string $task, string $suffix): string
    {
        return '/tasks/' . rawurlencode($task) . $suffix;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $base = rtrim($this->baseUrl, '/');
        $options = [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if (null !== $this->token && '' !== $this->token) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        if ('GET' !== $method) {
            $options['json'] = $payload;
        }

        try {
            $response = $this->httpClient->request($method, $base . $path, $options);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException(\sprintf('Unable to reach castor-api at %s: %s', $base, $exception->getMessage()), 0, $exception);
        }

        // 422 is a failed Castor task with the same JSON body as a success.
        if (422 === $status) {
            return $data;
        }

        if (409 === $status && \is_array($data['conflict'] ?? null)) {
            throw new RunConflictException(\is_string($data['error'] ?? null) ? $data['error'] : 'Another task is running.', $data['conflict']);
        }

        if ($status >= 400) {
            $message = \is_string($data['error'] ?? null) ? $data['error'] : 'castor-api error (HTTP ' . $status . ').';

            throw new \RuntimeException($message);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function isFailedTaskPayload(array $data): bool
    {
        if ('failed' === ($data['status'] ?? null)) {
            return true;
        }

        $exitCode = $data['exitCode'] ?? null;

        return \is_int($exitCode) && 0 !== $exitCode;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function taskFailureMessage(array $data): string
    {
        foreach (['stderr', 'stdout', 'error'] as $key) {
            $value = ConsoleOutput::withoutProgressBars(trim((string) ($data[$key] ?? '')));

            if ('' !== $value) {
                return $value;
            }
        }

        $exitCode = $data['exitCode'] ?? null;

        return \is_int($exitCode)
            ? \sprintf('castor-api task failed (exit code %d).', $exitCode)
            : 'castor-api task failed.';
    }
}
