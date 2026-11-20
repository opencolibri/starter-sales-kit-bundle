<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Repository;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKitBundle\Model\Shop;

/**
 * @extends InMemoryRepository<Shop>
 */
class ShopRepository extends InMemoryRepository
{
    public const SORTABLE_FIELDS = ['name', 'slug', 'dataset', 'theme', 'productCount', 'hasFixtures', 'importedAt'];

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {
        parent::__construct();
    }

    protected function load(): array
    {
        return array_map(Shop::fromArray(...), $this->castorApi->listShops());
    }

    /** Name or slug containing the given text. */
    public function withSearch(string $text): static
    {
        return $this->filter(static fn(Shop $shop): bool => self::contains($shop->name, $text) || self::contains($shop->slug, $text));
    }

    public function withDataset(string $dataset): static
    {
        return $this->filter(static fn(Shop $shop): bool => $shop->dataset === $dataset);
    }

    public function withTheme(string $theme): static
    {
        return $this->filter(static fn(Shop $shop): bool => $shop->theme === $theme);
    }

    public function withPrivate(bool $private): static
    {
        return $this->filter(static fn(Shop $shop): bool => $shop->private === $private);
    }

    public function withFixtures(bool $hasFixtures): static
    {
        return $this->filter(static fn(Shop $shop): bool => $shop->hasFixtures === $hasFixtures);
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        if (!\in_array($field, self::SORTABLE_FIELDS, true)) {
            return $this;
        }

        return $this->sortBy(static fn(Shop $shop): mixed => $shop->{$field}, $direction);
    }

    /**
     * @return list<string>
     */
    public function findThemes(): array
    {
        $themes = array_values(array_unique(array_map(static fn(Shop $shop): string => $shop->theme, $this->findAll())));
        sort($themes);

        return $themes;
    }
}
