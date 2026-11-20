<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional\Component;

use OpenColibri\Starter\SalesKitBundle\Tests\Support\FakeCastorApi;
use OpenColibri\Starter\SalesKitBundle\Twig\Components\ThemePicker;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class HeaderLayoutPickerTest extends ComponentTestCase
{
    public function testItChangesTheHeaderLayoutOfTheTheme(): void
    {
        $component = $this->picker('cellar')
            ->set('menu', 'below')
            ->set('search', 'icon')
            ->call('apply');

        static::assertSame([['theme' => 'cellar', 'menu' => 'below', 'search' => 'icon']], array_column(FakeCastorApi::calls('sales-kit:theme:header'), 'payload'));
        static::assertStringContainsString('The cellar theme uses the new header layout.', (string) $component->render());
        static::assertSame(['menu' => 'below', 'search' => 'icon'], array_intersect_key($component->component()->headers['cellar'], ['menu' => true, 'search' => true]));
    }

    public function testItRefusesAPlacementTheThemeDoesNotSupport(): void
    {
        $component = $this->picker('drive')->set('menu', 'inline')->call('apply');

        static::assertSame([], FakeCastorApi::calls('sales-kit:theme:header'));
        static::assertStringContainsString('The &quot;drive&quot; theme does not support this header layout.', (string) $component->render());
    }

    public function testItOnlyOffersThePlacementsOfTheTheme(): void
    {
        $render = $this->picker('drive')->render()->crawler();

        static::assertSame(['bar'], $render->filter('#header-menu option')->each(static fn($option): string => (string) $option->attr('value')));
    }

    public function testItFollowsTheThemePickedOnThePage(): void
    {
        $component = $this->picker('cellar')->emit(ThemePicker::APPLIED_EVENT, ['theme' => 'drive']);

        static::assertSame('drive', $component->component()->theme);
        static::assertSame('bar', $component->component()->menu);
        static::assertStringContainsString('Belongs to the drive theme', (string) $component->render());
    }

    public function testItHasNoOptionsForTheSyliusLook(): void
    {
        $render = (string) $this->picker('default')->render();

        static::assertStringContainsString('The Sylius look has no header layout options', $render);
        static::assertStringNotContainsString('header-menu', $render);
    }

    public function testItAsksToReinstallAThemeWithoutHeaderLayout(): void
    {
        $render = (string) $this->picker('canvas', withCanvas: false)->render();

        static::assertStringContainsString('was installed before the header layout existed', $render);
    }

    private function picker(string $theme, bool $withCanvas = true): TestLiveComponent
    {
        $headers = FakeCastorApi::client()->themeCatalog()['headers'];

        if (!$withCanvas) {
            unset($headers['canvas']);
        }

        return $this->createAdminLiveComponent('SalesKit:HeaderLayoutPicker', ['headers' => $headers, 'theme' => $theme]);
    }
}
