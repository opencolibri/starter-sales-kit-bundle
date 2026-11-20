<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Import;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Progress of the image steps of a run: castor-api only returns the output of a task once it finishes, so the import
 * tasks write it to .castor/import/var/.progress/<key>.json (see docs/import/extending.md of sylius-starter).
 */
final class RunProgress
{
    /** Older files come from a step that did not end cleanly. */
    private const STALE_AFTER_SECONDS = 1800;

    public function __construct(
        #[Autowire('%opencolibri_sales_kit.import_var_dir%')]
        private readonly string $importVarDir,
    ) {}

    /**
     * The shop or the dataset a task works on, or null when the task reports no progress.
     *
     * @param array<string, mixed> $payload
     */
    public static function key(string $task, array $payload): ?string
    {
        [$prefix, $field] = match (true) {
            str_starts_with($task, 'sylius:dataset:') => ['dataset-', 'dataset'],
            'sylius:shop:generate' === $task => ['shop-', 'shop'],
            default => [null, null],
        };

        $slug = null !== $field && \is_scalar($payload[$field] ?? null) ? trim((string) $payload[$field]) : '';

        return null !== $prefix && 1 === preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) ? $prefix . $slug : null;
    }

    /**
     * @return array{label: string, done: int, total: int, current: string|null, percent: int}|null
     */
    public function read(?string $key): ?array
    {
        if (null === $key) {
            return null;
        }

        $path = rtrim($this->importVarDir, '/\\') . '/.progress/' . $key . '.json';
        $json = is_file($path) ? @file_get_contents($path) : false;
        $data = false !== $json ? json_decode($json, true) : null;

        if (!\is_array($data) || (int) ($data['updatedAt'] ?? 0) < time() - self::STALE_AFTER_SECONDS) {
            return null;
        }

        $total = max(0, (int) ($data['total'] ?? 0));
        $done = min($total, max(0, (int) ($data['done'] ?? 0)));
        $current = \is_string($data['current'] ?? null) && '' !== $data['current'] ? $data['current'] : null;

        return [
            'label' => (string) ($data['label'] ?? ''),
            'done' => $done,
            'total' => $total,
            'current' => $current,
            'percent' => $total > 0 ? (int) floor($done * 100 / $total) : 0,
        ];
    }
}
