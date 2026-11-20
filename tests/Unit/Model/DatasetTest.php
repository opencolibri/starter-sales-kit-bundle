<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Unit\Model;

use OpenColibri\Starter\SalesKitBundle\Castor\RunDescription;
use OpenColibri\Starter\SalesKitBundle\Model\Dataset;
use OpenColibri\Starter\SalesKitBundle\Model\Shop;
use PHPUnit\Framework\TestCase;

final class DatasetTest extends TestCase
{
    public function testItTellsWhereADatasetComesFrom(): void
    {
        static::assertSame(Dataset::SOURCE_READY_MADE, Dataset::fromArray(['slug' => 'cellar', 'name' => 'Cellar', 'builtIn' => true, 'source' => 'ai'])->getOrigin());
        static::assertSame('existing', Dataset::fromArray(['slug' => 'atelier', 'name' => 'Atelier', 'source' => 'existing'])->getOrigin());
    }

    public function testItTellsWhenImagesAreMissing(): void
    {
        static::assertTrue(Dataset::fromArray(['slug' => 'a', 'name' => 'A', 'productCount' => 3, 'imageCount' => 2])->hasMissingImages());
        static::assertFalse(Dataset::fromArray(['slug' => 'a', 'name' => 'A', 'productCount' => 3, 'imageCount' => 3])->hasMissingImages());
    }

    public function testItReadsAShopFromCastorApi(): void
    {
        $shop = Shop::fromArray(['slug' => 'cellar', 'name' => 'Cellar', 'dataset' => '', 'private' => true, 'hasFixtures' => true]);

        static::assertNull($shop->dataset);
        static::assertSame('default', $shop->theme);
        static::assertTrue($shop->private);
    }

    public function testItDescribesARun(): void
    {
        static::assertSame('Load into Sylius', RunDescription::label('sylius:shop:load'));
        static::assertSame('custom:task', RunDescription::label('custom:task'));
        static::assertSame('cellar', RunDescription::subject(['shop' => ' cellar ', 'theme' => 'drive']));
        static::assertSame('drive', RunDescription::subject(['shop' => '', 'theme' => 'drive']));
        static::assertSame('', RunDescription::subject([]));
    }
}
