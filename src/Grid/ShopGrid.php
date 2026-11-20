<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Grid;

use OpenColibri\Starter\SalesKitBundle\Grid\Provider\ShopGridProvider;
use OpenColibri\Starter\SalesKitBundle\Model\Shop;
use OpenColibri\Starter\SalesKitBundle\Repository\DatasetRepository;
use OpenColibri\Starter\SalesKitBundle\Repository\ShopRepository;
use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Bundle\GridBundle\Builder\Filter\BooleanFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\SelectFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Component\Grid\Attribute\AsGrid;

/**
 * The demo shops. The data comes from castor-api: ShopGridProvider applies the filters and the sorting in memory,
 * through ShopRepository. An instance changes it like any Sylius grid (a field or filter more, one disabled...).
 */
#[AsGrid(resourceClass: Shop::class, name: self::NAME, provider: ShopGridProvider::class)]
final readonly class ShopGrid
{
    public const NAME = 'opencolibri_sales_kit_shop';

    private const FIELDS = '@OpenColibriStarterSalesKit/grid/field/';

    public function __construct(
        private ShopRepository $shops,
        private DatasetRepository $datasets,
    ) {}

    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->setLimits([10, 25, 50])
            ->addOrderBy('name', 'asc')
            ->withFields(
                TwigField::create('name', self::FIELDS . 'shop/name.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.name')
                    ->setPath('.')
                    ->setSortable(true, 'name')
                    ->withOptions(['vars' => ['mobile' => 'title']]),
                TwigField::create('dataset', self::FIELDS . 'muted.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.dataset')
                    ->setSortable(true),
                TwigField::create('theme', self::FIELDS . 'badge.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.theme')
                    ->setSortable(true),
                TwigField::create('productCount', self::FIELDS . 'shop/products.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.products')
                    ->setPath('.')
                    ->setSortable(true, 'productCount')
                    ->withOptions(['vars' => ['th_class' => 'text-end', 'td_class' => 'text-end tabular-nums']]),
                TwigField::create('hasFixtures', self::FIELDS . 'fixtures.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.fixtures')
                    ->setSortable(true),
                TwigField::create('channelCode', self::FIELDS . 'code.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.channel'),
                TwigField::create('actions', self::FIELDS . 'shop/actions.html.twig')
                    ->setLabel('')
                    ->setPath('.')
                    ->withOptions(['vars' => ['td_class' => 'text-end', 'mobile' => 'meta']]),
            )
            ->withFilters(
                StringFilter::create('search', ['name', 'slug'], StringFilter::TYPE_CONTAINS)
                    ->setLabel('sylius.ui.search'),
                SelectFilter::create('dataset', $this->datasets->findChoices())
                    ->setLabel('opencolibri_sales_kit.ui.dataset'),
                SelectFilter::create('theme', array_combine($this->shops->findThemes(), $this->shops->findThemes()))
                    ->setLabel('opencolibri_sales_kit.ui.theme'),
                BooleanFilter::create('hasFixtures')
                    ->setLabel('opencolibri_sales_kit.ui.fixtures_generated'),
                BooleanFilter::create('private')
                    ->setLabel('opencolibri_sales_kit.ui.private'),
            )
        ;
    }
}
