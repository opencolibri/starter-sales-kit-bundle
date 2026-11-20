<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKitBundle\Import\ProjectConfigStorage;
use OpenColibri\Starter\SalesKitBundle\Import\ShopImageStorage;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
    ;

    $services->load('OpenColibri\\Starter\\SalesKitBundle\\', '../src/')
        ->exclude(['../src/OpenColibriStarterSalesKitBundle.php', '../src/Model/'])
    ;

    $services->load('OpenColibri\\Starter\\SalesKitBundle\\Controller\\', '../src/Controller/')
        ->tag('controller.service_arguments')
    ;

    $services->set(CastorApiClient::class)
        ->arg('$baseUrl', param('opencolibri_sales_kit.castor_api.url'))
        ->arg('$token', param('opencolibri_sales_kit.castor_api.token'))
    ;

    $services->set(ShopImageStorage::class)
        ->arg('$importVarDir', param('opencolibri_sales_kit.import_var_dir'))
    ;

    $services->set(ProjectConfigStorage::class)
        ->arg('$importVarDir', param('opencolibri_sales_kit.import_var_dir'))
    ;
};
