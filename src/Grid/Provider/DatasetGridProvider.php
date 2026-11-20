<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Grid\Provider;

use OpenColibri\Starter\SalesKitBundle\Repository\DatasetRepository;
use Pagerfanta\PagerfantaInterface;
use Sylius\Component\Grid\Data\DataProviderInterface;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Parameters;

/**
 * Turns the filters, sorting and page of the dataset grid into a query on the dataset repository.
 */
final readonly class DatasetGridProvider implements DataProviderInterface
{
    public function __construct(
        private DatasetRepository $datasets,
    ) {}

    public function getData(Grid $grid, Parameters $parameters): PagerfantaInterface
    {
        $datasets = $this->datasets;

        if (null !== $search = GridParameters::string($parameters, 'search')) {
            $datasets = $datasets->withSearch($search);
        }

        if (null !== $origin = GridParameters::choice($parameters, 'origin')) {
            $datasets = $datasets->withOrigin($origin);
        }

        if (null !== $theme = GridParameters::choice($parameters, 'theme')) {
            $datasets = $datasets->withTheme($theme);
        }

        if (null !== $missingImages = GridParameters::boolean($parameters, 'missingImages')) {
            $datasets = $datasets->withMissingImages($missingImages);
        }

        foreach (GridParameters::sorting($grid, $parameters) as $field => $direction) {
            $datasets = $datasets->orderBy($field, $direction);
        }

        return $datasets->paginate(GridParameters::page($parameters), GridParameters::limit($grid, $parameters));
    }
}
