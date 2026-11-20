<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Menu;

use Knp\Menu\ItemInterface;
use Sylius\AdminUi\Knp\Menu\MenuBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Sidebar items of the sales kit. An instance adds its own items by decorating "sylius_admin_ui.knp.menu_builder"
 * too: its decorator runs after this one and gets the items below.
 */
#[AsDecorator(decorates: 'sylius_admin_ui.knp.menu_builder')]
final class AdminMenuBuilder implements MenuBuilderInterface
{
    public function __construct(
        private readonly MenuBuilderInterface $menuBuilder,
    ) {}

    public function createMenu(array $options): ItemInterface
    {
        $menu = $this->menuBuilder->createMenu($options);

        $menu->addChild('dashboard', ['route' => 'sylius_admin_ui_dashboard'])
            ->setLabel('sylius.ui.dashboard')
            ->setLabelAttribute('icon', 'lucide:layout-dashboard')
        ;

        $menu->addChild('sales_kit', ['extras' => ['section' => true]])
            ->setLabel('opencolibri_sales_kit.ui.demo_shops')
        ;

        $menu->addChild('shops', [
            'route' => 'opencolibri_sales_kit_shop_index',
            'extras' => ['routes' => [['pattern' => '/^opencolibri_sales_kit_shop_/']]],
        ])
            ->setLabel('opencolibri_sales_kit.ui.shops')
            ->setLabelAttribute('icon', 'lucide:store')
        ;

        $menu->addChild('datasets', [
            'route' => 'opencolibri_sales_kit_dataset_index',
            'extras' => ['routes' => [['pattern' => '/^opencolibri_sales_kit_dataset_/']]],
        ])
            ->setLabel('opencolibri_sales_kit.ui.datasets')
            ->setLabelAttribute('icon', 'lucide:database')
        ;

        return $menu;
    }
}
