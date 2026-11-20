<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional;

use OpenColibri\Starter\SalesKitBundle\Tests\Support\FakeCastorApi;

final class DashboardTest extends WebTestCase
{
    public function testItAsksAnonymousVisitorsToLogIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testItShowsTheLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
    }

    public function testItShowsTheFiguresOfTheDemoShops(): void
    {
        $client = static::createAdminClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $text = (string) preg_replace('/\s+/', ' ', $crawler->filter('main')->text());
        static::assertStringContainsString('Shops 2', $text);
        static::assertStringContainsString('With fixtures 1', $text);
        static::assertStringContainsString('Private shops 1', $text);
        static::assertStringContainsString('Datasets 2', $text);
    }

    public function testItStillRendersWhenCastorApiFails(): void
    {
        FakeCastorApi::fail('sylius:shop:list', 'castor-api is down.');

        static::createAdminClient()->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    public function testItShowsTheSidebarItemsOfTheSalesKit(): void
    {
        static::createAdminClient()->request('GET', '/');

        self::assertSelectorExists('a[href="/shops"]');
        self::assertSelectorExists('a[href="/datasets"]');
    }
}
