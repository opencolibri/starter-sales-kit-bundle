<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Dataset;

/**
 * What a dataset holds: its collections and its products, as written in its collections.yaml and products.yaml.
 * Images are file names of its media/ directory, or null when the image was not downloaded yet.
 */
final readonly class DatasetCatalog
{
    /**
     * @param list<array{name: string, description: string|null, image: string|null, productCount: int}> $collections
     * @param list<array{title: string, description: string|null, collection: string|null, price: float|null, image: string|null}> $products
     */
    public function __construct(
        public array $collections,
        public array $products,
    ) {}

    /**
     * @return list<array{title: string, description: string|null, collection: string|null, price: float|null, image: string|null}>
     */
    public function productsOf(?string $collection): array
    {
        if (null === $collection) {
            return $this->products;
        }

        return array_values(array_filter($this->products, static fn(array $product): bool => $product['collection'] === $collection));
    }
}
