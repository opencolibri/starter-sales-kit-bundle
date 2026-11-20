<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Grid\Provider;

use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Parameters;

/**
 * Reads the page, limit, sorting and criteria of a grid request, with the defaults of the grid definition.
 */
final class GridParameters
{
    public static function page(Parameters $parameters): int
    {
        return max(1, (int) $parameters->get('page', 1));
    }

    public static function limit(Grid $grid, Parameters $parameters): int
    {
        $limits = $grid->getLimits();
        $limit = (int) $parameters->get('limit', $limits[0] ?? 10);

        return [] === $limits || \in_array($limit, $limits, true) ? max(1, $limit) : $limits[0];
    }

    /**
     * @return array<string, string> field => "asc"|"desc"
     */
    public static function sorting(Grid $grid, Parameters $parameters): array
    {
        $sorting = $parameters->get('sorting', $grid->getSorting());

        return \is_array($sorting) ? array_filter($sorting, \is_string(...)) : [];
    }

    /**
     * The text of a string filter: "criteria[search][value]=…" or "criteria[search]=…".
     */
    public static function string(Parameters $parameters, string $name): ?string
    {
        $value = self::criterion($parameters, $name);
        $value = \is_array($value) ? ($value['value'] ?? null) : $value;

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * The choice of a select filter, or null for "all".
     */
    public static function choice(Parameters $parameters, string $name): ?string
    {
        $value = self::criterion($parameters, $name);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * A boolean filter: true, false, or null for "all".
     */
    public static function boolean(Parameters $parameters, string $name): ?bool
    {
        return match (self::criterion($parameters, $name)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private static function criterion(Parameters $parameters, string $name): mixed
    {
        $criteria = $parameters->get('criteria', []);

        return \is_array($criteria) ? ($criteria[$name] ?? null) : null;
    }
}
