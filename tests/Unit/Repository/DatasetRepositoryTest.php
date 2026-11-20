<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Unit\Repository;

use OpenColibri\Starter\SalesKitBundle\Model\Dataset;
use OpenColibri\Starter\SalesKitBundle\Repository\DatasetRepository;
use OpenColibri\Starter\SalesKitBundle\Tests\Support\FakeCastorApi;
use PHPUnit\Framework\TestCase;

final class DatasetRepositoryTest extends TestCase
{
    private DatasetRepository $datasets;

    protected function setUp(): void
    {
        FakeCastorApi::reset();
        $this->datasets = new DatasetRepository(FakeCastorApi::client());
    }

    public function testItFiltersTheDatasets(): void
    {
        static::assertSame(['my-catalog'], self::slugs($this->datasets->withSearch('built from an AI')->getResult()));
        static::assertSame(['cellar'], self::slugs($this->datasets->withOrigin(Dataset::SOURCE_READY_MADE)->getResult()));
        static::assertSame(['my-catalog'], self::slugs($this->datasets->withOrigin('ai')->getResult()));
        static::assertSame(['cellar'], self::slugs($this->datasets->withTheme('cellar')->getResult()));
        static::assertSame(['my-catalog'], self::slugs($this->datasets->withMissingImages(true)->getResult()));
    }

    public function testItSortsByOrigin(): void
    {
        static::assertSame(['my-catalog', 'cellar'], self::slugs($this->datasets->orderBy('origin')->getResult()));
        static::assertSame(['cellar', 'my-catalog'], self::slugs($this->datasets->orderBy('productCount', 'desc')->getResult()));
    }

    /**
     * @param list<Dataset> $datasets
     *
     * @return list<string>
     */
    private static function slugs(array $datasets): array
    {
        return array_map(static fn(Dataset $dataset): string => $dataset->slug, $datasets);
    }
}
