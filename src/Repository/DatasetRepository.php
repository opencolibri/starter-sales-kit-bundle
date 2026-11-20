<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Repository;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKitBundle\Model\Dataset;

/**
 * @extends InMemoryRepository<Dataset>
 */
class DatasetRepository extends InMemoryRepository
{
    public const SORTABLE_FIELDS = ['name', 'slug', 'origin', 'productCount', 'imageCount', 'collectionCount'];

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {
        parent::__construct();
    }

    protected function load(): array
    {
        return array_map(Dataset::fromArray(...), $this->castorApi->listDatasets());
    }

    /** Name, slug or description containing the given text. */
    public function withSearch(string $text): static
    {
        return $this->filter(static fn(Dataset $dataset): bool => self::contains($dataset->name, $text)
            || self::contains($dataset->slug, $text)
            || self::contains($dataset->description, $text));
    }

    /** "ready-made", "ai" or "existing". */
    public function withOrigin(string $origin): static
    {
        return $this->filter(static fn(Dataset $dataset): bool => $dataset->getOrigin() === $origin);
    }

    public function withTheme(string $theme): static
    {
        return $this->filter(static fn(Dataset $dataset): bool => $dataset->theme === $theme);
    }

    public function withMissingImages(bool $missing): static
    {
        return $this->filter(static fn(Dataset $dataset): bool => $dataset->hasMissingImages() === $missing);
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        if (!\in_array($field, self::SORTABLE_FIELDS, true)) {
            return $this;
        }

        return $this->sortBy(static fn(Dataset $dataset): mixed => 'origin' === $field ? $dataset->getOrigin() : $dataset->{$field}, $direction);
    }

    /**
     * @return array<string, string> name => slug
     */
    public function findChoices(): array
    {
        $choices = [];

        foreach ($this->orderBy('name')->getResult() as $dataset) {
            $choices[$dataset->name] = $dataset->slug;
        }

        return $choices;
    }

    /**
     * @return list<string>
     */
    public function findThemes(): array
    {
        $themes = array_values(array_unique(array_filter(array_map(static fn(Dataset $dataset): ?string => $dataset->theme, $this->findAll()))));
        sort($themes);

        return $themes;
    }
}
