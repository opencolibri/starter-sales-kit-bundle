<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The sales kit admin: shops, datasets and the castor-api runs that build them, in a Sylius Shadcn Admin UI panel.
 *
 * An instance is a small Symfony app that requires this bundle, imports "config/app.php" (hooks), the routes, and
 * owns everything that differs from one sales kit to another: users, branding, colors, domains, extra pages.
 */
final class OpenColibriStarterSalesKitBundle extends AbstractBundle
{
    protected string $extensionAlias = 'opencolibri_sales_kit';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('castor_api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')->defaultValue('%env(CASTOR_API_URL)%')->info('Base URL of castor-api, which runs the import tasks.')->end()
                        ->scalarNode('token')->defaultValue('%env(default::CASTOR_API_TOKEN)%')->info('Bearer token of castor-api (empty: no auth).')->end()
                    ->end()
                ->end()
                ->scalarNode('shop_root_domain')
                    ->defaultValue('%env(SHOP_ROOT_DOMAIN)%')
                    ->info('Demo shops are served on {slug}.<shop_root_domain>.')
                ->end()
                ->scalarNode('import_var_dir')
                    ->defaultValue('%env(IMPORT_VAR_DIR)%')
                    ->info('The .castor/import/var directory shared with castor-api (project.yaml, images, progress).')
                ->end()
                ->scalarNode('import_export_dir')
                    ->defaultValue('%env(IMPORT_EXPORT_DIR)%')
                    ->info('Where castor-api writes the dataset zips.')
                ->end()
                ->scalarNode('import_datasets_dir')
                    ->defaultValue('%env(default::IMPORT_DATASETS_DIR)%')
                    ->info('The .castor/import/datasets directory of the stack (read only): the catalog of the datasets built here. Empty: their page only shows their figures.')
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // <twig:SalesKit:RunMonitor />, component('SalesKit:ShopActions')...
        $builder->prependExtensionConfig('twig_component', [
            // Required by TwigComponent, set by its Flex recipe: the instance may set its own.
            'anonymous_template_directory' => 'components/',
            'defaults' => [
                'OpenColibri\\Starter\\SalesKitBundle\\Twig\\Components\\' => [
                    'template_directory' => '@OpenColibriStarterSalesKit/components/',
                    'name_prefix' => 'SalesKit',
                ],
            ],
        ]);

        // <twig:sales_kit:action_button>, <twig:sales_kit:message>: usable inside other components, unlike macros.
        $builder->prependExtensionConfig('sylius_twig_extra', [
            'twig_ux' => [
                'anonymous_component_template_prefixes' => [
                    'sales_kit' => '@OpenColibriStarterSalesKit/components/ui',
                ],
            ],
        ]);
    }

    /**
     * @param array{castor_api: array{url: string, token: string}, shop_root_domain: string, import_var_dir: string, import_export_dir: string, import_datasets_dir: string|null} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('opencolibri_sales_kit.castor_api.url', $config['castor_api']['url'])
            ->set('opencolibri_sales_kit.castor_api.token', $config['castor_api']['token'])
            ->set('opencolibri_sales_kit.shop_root_domain', $config['shop_root_domain'])
            ->set('opencolibri_sales_kit.import_var_dir', $config['import_var_dir'])
            ->set('opencolibri_sales_kit.import_export_dir', $config['import_export_dir'])
            ->set('opencolibri_sales_kit.import_datasets_dir', $config['import_datasets_dir'])
        ;

        $container->import('../config/services.php');
    }
}
