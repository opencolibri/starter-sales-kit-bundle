<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Castor;

/**
 * Human-readable names for the castor-api runs shown in the admin.
 */
final class RunDescription
{
    private const TASK_LABELS = [
        'sylius:shop:generate' => 'Generate fixtures',
        'sylius:shop:load' => 'Load into Sylius',
        'sylius:shop:delete' => 'Delete shop',
        'sylius:dataset:build' => 'Build dataset',
        'sylius:dataset:images' => 'Store dataset images',
    ];

    public static function label(string $task): string
    {
        return self::TASK_LABELS[$task] ?? $task;
    }

    /**
     * What the run works on: the shop, the theme or the dataset.
     *
     * @param array<string, mixed> $payload
     */
    public static function subject(array $payload): string
    {
        foreach (['shop', 'theme', 'dataset', 'name'] as $key) {
            if (\is_scalar($payload[$key] ?? null) && '' !== trim((string) $payload[$key])) {
                return trim((string) $payload[$key]);
            }
        }

        return '';
    }
}
