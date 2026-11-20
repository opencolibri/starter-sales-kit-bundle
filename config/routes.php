<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Pages of the sales kit. Import them next to the Sylius Admin UI routes (login, logout, dashboard):
 *
 *     opencolibri_sales_kit:
 *         resource: '@OpenColibriStarterSalesKitBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../src/Controller/', 'attribute');
};
