<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional\Component;

use OpenColibri\Starter\SalesKitBundle\Tests\Support\FakeCastorApi;
use OpenColibri\Starter\SalesKitBundle\Twig\Components\ThemePicker;

final class ThemePickerTest extends ComponentTestCase
{
    private const THEMES = ['default', 'canvas', 'cellar', 'drive'];

    public function testItPointsTheShopToTheChosenTheme(): void
    {
        $component = $this->createAdminLiveComponent('SalesKit:ThemePicker', ['project' => 'cellar', 'themes' => self::THEMES, 'current' => 'cellar'])
            ->set('theme', 'drive')
            ->call('apply');

        static::assertSame([['shop' => 'cellar', 'theme' => 'drive']], array_column(FakeCastorApi::calls('sylius:shop:theme'), 'payload'));
        $this->assertComponentEmitEvent($component, ThemePicker::APPLIED_EVENT)->withData(['theme' => 'drive']);
        static::assertStringContainsString('The shop now uses the drive theme.', (string) $component->render());
        static::assertSame('drive', $component->component()->currentTheme);
    }

    public function testItRefusesAnUnknownTheme(): void
    {
        $component = $this->createAdminLiveComponent('SalesKit:ThemePicker', ['project' => 'cellar', 'themes' => self::THEMES, 'current' => 'cellar'])
            ->set('theme', 'nope')
            ->call('apply');

        static::assertSame([], FakeCastorApi::calls('sylius:shop:theme'));
        $this->assertComponentNotEmitEvent($component, ThemePicker::APPLIED_EVENT);
        static::assertStringContainsString('Unknown theme &quot;nope&quot;.', (string) $component->render());
    }

    public function testItShowsTheErrorOfCastorApi(): void
    {
        FakeCastorApi::fail('sylius:shop:theme', 'Unknown shop "cellar".');

        $component = $this->createAdminLiveComponent('SalesKit:ThemePicker', ['project' => 'cellar', 'themes' => self::THEMES, 'current' => 'cellar'])
            ->set('theme', 'drive')
            ->call('apply');

        $this->assertComponentNotEmitEvent($component, ThemePicker::APPLIED_EVENT);
        static::assertStringContainsString('Unknown shop &quot;cellar&quot;.', (string) $component->render());
        static::assertSame('cellar', $component->component()->currentTheme);
    }

    public function testItNamesTheThemeToApply(): void
    {
        $component = $this->createAdminLiveComponent('SalesKit:ThemePicker', ['project' => 'cellar', 'themes' => self::THEMES, 'current' => 'cellar']);

        static::assertStringContainsString('Apply theme', (string) $component->render());
        static::assertStringContainsString('Apply Drive', (string) $component->set('theme', 'drive')->render());
    }
}
