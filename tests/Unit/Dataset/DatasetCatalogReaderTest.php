<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Unit\Dataset;

use OpenColibri\Starter\SalesKitBundle\Dataset\DatasetCatalogReader;
use OpenColibri\Starter\SalesKitBundle\Tests\Application\Kernel;
use PHPUnit\Framework\TestCase;

final class DatasetCatalogReaderTest extends TestCase
{
    private DatasetCatalogReader $reader;

    protected function setUp(): void
    {
        $this->reader = new DatasetCatalogReader(Kernel::FIXTURES_DIR . '/import-datasets');
    }

    public function testItReadsADatasetBuiltByTheStack(): void
    {
        $catalog = $this->reader->read('my-catalog');

        static::assertNotNull($catalog);
        static::assertSame([
            ['name' => 'Mugs', 'description' => null, 'image' => 'collection_mugs.webp', 'productCount' => 2],
            ['name' => 'Plates', 'description' => null, 'image' => null, 'productCount' => 1],
        ], $catalog->collections);
        static::assertSame(['title' => 'Blue mug', 'description' => 'A blue mug.', 'collection' => 'Mugs', 'price' => 12.5, 'image' => 'blue_mug.webp'], $catalog->products[0]);
        // A remote image not downloaded yet, no price.
        static::assertSame(['title' => 'Dinner plate', 'description' => null, 'collection' => 'Plates', 'price' => null, 'image' => null], $catalog->products[2]);
    }

    public function testItFiltersTheProductsOfACollection(): void
    {
        $catalog = $this->reader->read('my-catalog');

        static::assertNotNull($catalog);
        static::assertSame(['Blue mug', 'Green mug'], array_column($catalog->productsOf('Mugs'), 'title'));
        static::assertCount(3, $catalog->productsOf(null));
        static::assertSame([], $catalog->productsOf('Bowls'));
    }

    public function testItReadsTheReadyMadeDatasetsFromOpenColibriStarterDatasets(): void
    {
        $catalog = (new DatasetCatalogReader(null))->read('cellar');

        static::assertNotNull($catalog);
        static::assertCount(17, $catalog->products);
        static::assertNotNull($catalog->products[0]['image']);
    }

    public function testItHasNoCatalogForAnUnknownOrUnreadableDataset(): void
    {
        static::assertNull($this->reader->read('unknown'));
        static::assertNull($this->reader->read('../import-datasets/my-catalog'));
        static::assertNull((new DatasetCatalogReader(null))->read('my-catalog'));
    }

    public function testItOnlyServesTheMediaOfTheDataset(): void
    {
        static::assertSame(Kernel::FIXTURES_DIR . '/import-datasets/my-catalog/media/blue_mug.webp', $this->reader->mediaPath('my-catalog', 'blue_mug.webp'));
        static::assertNull($this->reader->mediaPath('my-catalog', 'missing.webp'));
        static::assertNull($this->reader->mediaPath('my-catalog', '../products.yaml'));
        static::assertNull($this->reader->mediaPath('my-catalog', 'products.yaml'));
        static::assertNull($this->reader->mediaPath('unknown', 'blue_mug.webp'));
    }
}
