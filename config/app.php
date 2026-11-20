<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * Hooks of the sales kit, imported by the instance (config/packages/opencolibri_sales_kit.yaml):
 *
 *     imports:
 *         - { resource: '@OpenColibriStarterSalesKitBundle/config/app.php' }
 *
 * It imports the Sylius Shadcn Admin UI hooks first, so it is the only file to import. Hooks defined by the instance
 * after this import win: branding, extra hookables on a page, hookables disabled...
 *
 * Every page renders two hooks: its own ("opencolibri_sales_kit.shop.show") and the common page layout
 * ("opencolibri_sales_kit.common.page": sidebar, navbar, flashes, header, run monitor). Child hooks are looked up
 * under both names, so a page only defines its title, actions and body.
 */
return static function (ContainerConfigurator $container): void {
    $container->import('@SyliusShadcnAdminUiBundle/config/app.php');

    $views = '@OpenColibriStarterSalesKit';
    $shadcn = '@SyliusShadcnAdminUi/shared/crud/common';
    $grid = '@SyliusShadcnAdminUi/shared/crud/index/content/grid';

    $hooks = [
        // Branding defaults: the instance sets its own name, subtitle and icon (or image).
        'sylius_admin.base#base_title' => [
            'default' => ['configuration' => ['title' => 'Sales Kit']],
        ],
        'sylius_admin.common.component.sidebar.logo' => [
            'image' => ['configuration' => ['name' => 'Sales Kit', 'subtitle' => 'Colibri Starter', 'icon' => 'lucide:store']],
        ],
        'sylius_admin.security.login.page.logo' => [
            'image' => ['configuration' => ['name' => 'Sales Kit', 'icon' => 'lucide:store']],
        ],

        // Dashboard: the figures of the demo shops and the runs in progress.
        'sylius_admin.dashboard.index.content.header.title_block' => [
            'title' => ['configuration' => ['subheader' => 'opencolibri_sales_kit.ui.dashboard_subheader']],
            'actions' => [
                'enabled' => true,
                'template' => $views . '/dashboard/actions.html.twig',
            ],
        ],
        'sylius_admin.dashboard.index.content' => [
            'opencolibri_sales_kit_stats' => ['template' => $views . '/dashboard/stats.html.twig', 'priority' => -10],
            'opencolibri_sales_kit_run_monitor' => [
                'template' => $views . '/shared/run_monitor.html.twig',
                'priority' => -100,
                'configuration' => ['reload_route' => 'sylius_admin_ui_dashboard'],
            ],
        ],

        // Common page layout.
        'opencolibri_sales_kit.common.page' => [
            'sidebar' => ['template' => $shadcn . '/sidebar.html.twig'],
            'navbar' => ['template' => $shadcn . '/navbar.html.twig'],
            'content' => ['template' => $shadcn . '/content.html.twig'],
        ],
        'opencolibri_sales_kit.common.page.content' => [
            'header' => ['template' => $shadcn . '/content/header.html.twig', 'priority' => 200],
            'flashes' => ['template' => $shadcn . '/content/flashes.html.twig', 'priority' => 100],
            'body' => ['template' => $views . '/shared/body.html.twig', 'priority' => 0],
            'run_monitor' => ['template' => $views . '/shared/run_monitor.html.twig', 'priority' => -100],
        ],
        'opencolibri_sales_kit.common.page.content.header' => [
            'breadcrumbs' => ['template' => $views . '/shared/breadcrumbs.html.twig', 'priority' => 100],
            'title_block' => ['template' => $shadcn . '/content/header/title_block.html.twig', 'priority' => 0],
        ],
        'opencolibri_sales_kit.common.page.content.header.title_block' => [
            'title' => ['template' => $views . '/shared/title.html.twig', 'priority' => 100],
        ],

        // Shops
        'opencolibri_sales_kit.shop.index.content.header.title_block' => [
            'actions' => ['template' => $views . '/shop/index/actions.html.twig'],
        ],
        // Sylius grid (ShopGrid): filters, table, empty state.
        'opencolibri_sales_kit.shop.index.content.body' => [
            'filters' => ['template' => $grid . '/filters.html.twig', 'priority' => 200],
            'grid' => ['template' => $grid . '/data_table.html.twig', 'priority' => 100],
            'no_results' => [
                'template' => $views . '/shared/grid/no_results.html.twig',
                'priority' => 0,
                'configuration' => [
                    'icon' => 'lucide:store',
                    'title' => 'opencolibri_sales_kit.ui.no_shops',
                    'description' => 'opencolibri_sales_kit.ui.no_shops_description',
                ],
            ],
        ],

        'opencolibri_sales_kit.shop.new.content.body' => [
            'form' => ['template' => $views . '/shop/new/form.html.twig'],
        ],

        'opencolibri_sales_kit.shop.show.content.header.title_block' => [
            'actions' => ['template' => $views . '/shop/show/actions.html.twig'],
        ],
        'opencolibri_sales_kit.shop.show.content.body' => [
            'warning' => ['template' => $views . '/shop/show/warning.html.twig', 'priority' => 400],
            'stats' => ['template' => $views . '/shop/show/stats.html.twig', 'priority' => 300],
            'columns' => ['template' => $views . '/shop/show/columns.html.twig', 'priority' => 200],
        ],
        'opencolibri_sales_kit.shop.show.content.body.main' => [
            'source' => ['template' => $views . '/shop/show/source.html.twig', 'priority' => 200],
            'actions' => ['template' => $views . '/shop/show/steps.html.twig', 'priority' => 100],
            'storefront' => ['template' => $views . '/shop/show/storefront.html.twig', 'priority' => 0],
        ],
        // The look of the storefront, side by side under the steps.
        'opencolibri_sales_kit.shop.show.content.body.main.storefront' => [
            'theme' => ['template' => $views . '/shop/show/theme.html.twig', 'priority' => 200],
            'header_layout' => ['template' => $views . '/shop/show/header_layout.html.twig', 'priority' => 100],
        ],
        'opencolibri_sales_kit.shop.show.content.body.aside' => [
            'access' => ['template' => $views . '/shop/show/access.html.twig', 'priority' => 400],
            'visibility' => ['template' => $views . '/shop/show/visibility.html.twig', 'priority' => 300],
            'delete' => ['template' => $views . '/shop/show/delete.html.twig', 'priority' => 100],
        ],

        // Datasets
        'opencolibri_sales_kit.dataset.index.content.header.title_block' => [
            'actions' => ['template' => $views . '/dataset/index/actions.html.twig'],
        ],
        // Sylius grid (DatasetGrid): filters, table, empty state.
        'opencolibri_sales_kit.dataset.index.content.body' => [
            'filters' => ['template' => $grid . '/filters.html.twig', 'priority' => 200],
            'grid' => ['template' => $grid . '/data_table.html.twig', 'priority' => 100],
            'no_results' => [
                'template' => $views . '/shared/grid/no_results.html.twig',
                'priority' => 0,
                'configuration' => [
                    'icon' => 'lucide:database',
                    'title' => 'opencolibri_sales_kit.ui.no_datasets',
                    'description' => 'opencolibri_sales_kit.ui.datasets_subheader',
                ],
            ],
        ],

        'opencolibri_sales_kit.dataset.show.content.header.title_block' => [
            'actions' => ['template' => $views . '/dataset/show/actions.html.twig'],
        ],
        'opencolibri_sales_kit.dataset.show.content.body' => [
            'stats' => ['template' => $views . '/dataset/show/stats.html.twig', 'priority' => 300],
            'columns' => ['template' => $views . '/shop/show/columns.html.twig', 'priority' => 200],
        ],
        'opencolibri_sales_kit.dataset.show.content.body.main' => [
            'catalog' => ['template' => $views . '/dataset/show/catalog.html.twig', 'priority' => 100],
        ],
        'opencolibri_sales_kit.dataset.show.content.body.aside' => [
            'about' => ['template' => $views . '/dataset/show/about.html.twig', 'priority' => 200],
            'shops' => ['template' => $views . '/dataset/show/shops.html.twig', 'priority' => 100],
        ],

        'opencolibri_sales_kit.dataset.new.content.body' => [
            'form' => ['template' => $views . '/dataset/new/form.html.twig'],
        ],
    ];

    $container->extension('sylius_twig_hooks', ['hooks' => $hooks]);
};
