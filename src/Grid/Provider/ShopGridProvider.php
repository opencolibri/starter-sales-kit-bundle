<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Grid\Provider;

use OpenColibri\Starter\SalesKitBundle\Repository\ShopRepository;
use Pagerfanta\PagerfantaInterface;
use Sylius\Component\Grid\Data\DataProviderInterface;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Parameters;

/**
 * Turns the filters, sorting and page of the shop grid into a query on the shop repository.
 */
final readonly class ShopGridProvider implements DataProviderInterface
{
    public function __construct(
        private ShopRepository $shops,
    ) {}

    public function getData(Grid $grid, Parameters $parameters): PagerfantaInterface
    {
        $shops = $this->shops;

        if (null !== $search = GridParameters::string($parameters, 'search')) {
            $shops = $shops->withSearch($search);
        }

        if (null !== $dataset = GridParameters::choice($parameters, 'dataset')) {
            $shops = $shops->withDataset($dataset);
        }

        if (null !== $theme = GridParameters::choice($parameters, 'theme')) {
            $shops = $shops->withTheme($theme);
        }

        if (null !== $private = GridParameters::boolean($parameters, 'private')) {
            $shops = $shops->withPrivate($private);
        }

        if (null !== $hasFixtures = GridParameters::boolean($parameters, 'hasFixtures')) {
            $shops = $shops->withFixtures($hasFixtures);
        }

        foreach (GridParameters::sorting($grid, $parameters) as $field => $direction) {
            $shops = $shops->orderBy($field, $direction);
        }

        return $shops->paginate(GridParameters::page($parameters), GridParameters::limit($grid, $parameters));
    }
}
