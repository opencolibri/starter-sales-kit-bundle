<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional\Component;

use OpenColibri\Starter\SalesKitBundle\Tests\Support\FakeCastorApi;
use OpenColibri\Starter\SalesKitBundle\Twig\Components\RunMonitor;

final class RunActionsTest extends ComponentTestCase
{
    public function testItStartsTheFixturesLoadAndHandsTheRunToTheMonitor(): void
    {
        $component = $this->createAdminLiveComponent('SalesKit:ShopActions', ['shop' => ['slug' => 'cellar', 'mode' => 'ai', 'hasProducts' => true, 'hasFixtures' => true]])
            ->call('load');

        static::assertSame([['shop' => 'cellar']], array_column(FakeCastorApi::calls('sylius:shop:load'), 'payload'));
        $this->assertComponentEmitEvent($component, RunMonitor::STARTED_EVENT)->withData(['runId' => 'run-1', 'task' => 'sylius:shop:load']);
    }

    public function testItGeneratesTheFixturesOfAnExistingSiteWithItsLimit(): void
    {
        $this->createAdminLiveComponent('SalesKit:ShopActions', ['shop' => ['slug' => 'atelier', 'mode' => 'existing', 'hasProducts' => true]])
            ->set('limit', 30)
            ->call('generate');

        static::assertSame([['shop' => 'atelier', 'subdomain' => 'atelier', 'limit' => 30]], array_column(FakeCastorApi::calls('sylius:shop:generate'), 'payload'));
    }

    public function testItChecksTheShopBeforeStartingARun(): void
    {
        $component = $this->createAdminLiveComponent('SalesKit:ShopActions', ['shop' => ['slug' => 'atelier', 'mode' => 'existing', 'hasProducts' => true, 'hasFixtures' => false]]);

        static::assertStringContainsString('Generate fixtures before loading them into Sylius.', (string) $component->call('load')->render());
        static::assertStringContainsString('between 1 and 500', (string) $component->set('limit', 0)->call('generate')->render());
        static::assertSame([], FakeCastorApi::calls());
    }

    public function testItNamesTheRunThatHoldsTheLock(): void
    {
        FakeCastorApi::conflict('sylius:shop:delete', 'sylius:shop:load', ['shop' => 'cellar']);

        $component = $this->createAdminLiveComponent('SalesKit:DeleteShop', ['slug' => 'cellar'])->call('delete');

        $this->assertComponentNotEmitEvent($component, RunMonitor::STARTED_EVENT);
        static::assertStringContainsString('&quot;Load into Sylius&quot; (cellar) is still running. Wait for it to finish before starting &quot;Delete shop&quot;.', (string) $component->render());
    }

    public function testItChangesTheVisibilityOfAShop(): void
    {
        $component = $this->createAdminLiveComponent('SalesKit:ShopVisibility', ['project' => 'cellar', 'private' => false])->call('makePrivate');

        static::assertSame([['shop' => 'cellar', 'visibility' => 'private']], array_column(FakeCastorApi::calls('sylius:shop:visibility'), 'payload'));
        static::assertTrue($component->component()->private);
    }
}
