<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Grid;

use OpenColibri\Starter\SalesKitBundle\Grid\Provider\DatasetGridProvider;
use OpenColibri\Starter\SalesKitBundle\Model\Dataset;
use OpenColibri\Starter\SalesKitBundle\Repository\DatasetRepository;
use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Bundle\GridBundle\Builder\Filter\BooleanFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\SelectFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Component\Grid\Attribute\AsGrid;

/**
 * The datasets shops are created from. Filters and sorting are applied by DatasetGridProvider, through
 * DatasetRepository.
 */
#[AsGrid(resourceClass: Dataset::class, name: self::NAME, provider: DatasetGridProvider::class)]
final readonly class DatasetGrid
{
    public const NAME = 'opencolibri_sales_kit_dataset';

    private const FIELDS = '@OpenColibriStarterSalesKit/grid/field/';

    public function __construct(
        private DatasetRepository $datasets,
    ) {}

    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $themes = $this->datasets->findThemes();

        $gridBuilder
            ->setLimits([10, 25, 50])
            ->addOrderBy('name', 'asc')
            ->withFields(
                TwigField::create('name', self::FIELDS . 'dataset/name.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.name')
                    ->setPath('.')
                    ->setSortable(true, 'name')
                    ->withOptions(['vars' => ['mobile' => 'title', 'wrap' => true]]),
                TwigField::create('origin', self::FIELDS . 'dataset/origin.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.source')
                    ->setPath('.')
                    ->setSortable(true, 'origin'),
                TwigField::create('productCount', self::FIELDS . 'number.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.products')
                    ->setSortable(true)
                    ->withOptions(['vars' => ['th_class' => 'text-end', 'td_class' => 'text-end tabular-nums']]),
                TwigField::create('imageCount', self::FIELDS . 'dataset/images.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.images')
                    ->setPath('.')
                    ->setSortable(true, 'imageCount')
                    ->withOptions(['vars' => ['th_class' => 'text-end', 'td_class' => 'text-end tabular-nums']]),
                TwigField::create('collectionCount', self::FIELDS . 'number.html.twig')
                    ->setLabel('opencolibri_sales_kit.ui.collections')
                    ->setSortable(true)
                    ->withOptions(['vars' => ['th_class' => 'text-end', 'td_class' => 'text-end tabular-nums']]),
                TwigField::create('actions', self::FIELDS . 'dataset/actions.html.twig')
                    ->setLabel('')
                    ->setPath('.')
                    ->withOptions(['vars' => ['td_class' => 'text-end', 'mobile' => 'meta']]),
            )
            ->withFilters(
                StringFilter::create('search', ['name', 'slug', 'description'], StringFilter::TYPE_CONTAINS)
                    ->setLabel('sylius.ui.search'),
                SelectFilter::create('origin', [
                    'opencolibri_sales_kit.ui.ready_made' => Dataset::SOURCE_READY_MADE,
                    'opencolibri_sales_kit.ui.ai_catalog' => 'ai',
                    'opencolibri_sales_kit.ui.existing_site' => 'existing',
                ])
                    ->setLabel('opencolibri_sales_kit.ui.source'),
                SelectFilter::create('theme', array_combine($themes, $themes))
                    ->setLabel('opencolibri_sales_kit.ui.theme'),
                BooleanFilter::create('missingImages')
                    ->setLabel('opencolibri_sales_kit.ui.missing_images'),
            )
        ;
    }
}
