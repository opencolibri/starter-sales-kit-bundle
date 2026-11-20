<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Unit\Repository;

use OpenColibri\Starter\SalesKitBundle\Model\Shop;
use OpenColibri\Starter\SalesKitBundle\Repository\ShopRepository;
use OpenColibri\Starter\SalesKitBundle\Tests\Support\FakeCastorApi;
use PHPUnit\Framework\TestCase;

final class ShopRepositoryTest extends TestCase
{
    private ShopRepository $shops;

    protected function setUp(): void
    {
        FakeCastorApi::reset();
        $this->shops = new ShopRepository(FakeCastorApi::client());
    }

    public function testItLoadsTheShopsOnceFromCastorApi(): void
    {
        static::assertSame(['cellar', 'atelier'], self::slugs($this->shops->findAll()));
        $this->shops->withSearch('cel')->getResult();

        static::assertCount(1, FakeCastorApi::calls('sylius:shop:list'));
    }

    public function testItFiltersWithoutChangingTheRepository(): void
    {
        static::assertSame(['atelier'], self::slugs($this->shops->withSearch('ATEL')->getResult()));
        static::assertSame(['cellar'], self::slugs($this->shops->withDataset('cellar')->getResult()));
        static::assertSame(['atelier'], self::slugs($this->shops->withPrivate(true)->getResult()));
        static::assertSame(['cellar'], self::slugs($this->shops->withFixtures(true)->getResult()));
        static::assertSame(['atelier'], self::slugs($this->shops->withTheme('default')->getResult()));
        static::assertSame([], $this->shops->withPrivate(true)->withFixtures(true)->getResult());
        static::assertCount(2, $this->shops->getResult());
    }

    public function testItSortsOnTheSortableFieldsOnly(): void
    {
        static::assertSame(['atelier', 'cellar'], self::slugs($this->shops->orderBy('name')->getResult()));
        static::assertSame(['cellar', 'atelier'], self::slugs($this->shops->orderBy('productCount', 'desc')->getResult()));
        static::assertSame(['cellar', 'atelier'], self::slugs($this->shops->orderBy('adminPassword')->getResult()));
    }

    public function testItPaginates(): void
    {
        $page = $this->shops->orderBy('name')->paginate(2, 1);

        static::assertSame(2, $page->getNbResults());
        static::assertSame(['cellar'], self::slugs(iterator_to_array($page->getCurrentPageResults(), false)));
    }

    public function testItListsTheThemesOfTheShops(): void
    {
        static::assertSame(['cellar', 'default'], $this->shops->findThemes());
    }

    public function testItForgetsTheShopsOnReset(): void
    {
        $this->shops->findAll();
        $this->shops->reset();
        $this->shops->findAll();

        static::assertCount(2, FakeCastorApi::calls('sylius:shop:list'));
    }

    /**
     * @param list<Shop> $shops
     *
     * @return list<string>
     */
    private static function slugs(array $shops): array
    {
        return array_map(static fn(Shop $shop): string => $shop->slug, $shops);
    }
}
