<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional;

use OpenColibri\Starter\SalesKitBundle\Tests\Support\FakeCastorApi;
use Symfony\Component\DomCrawler\Crawler;

final class ShopTest extends WebTestCase
{
    public function testItListsTheShops(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/shops');

        self::assertResponseIsSuccessful();
        static::assertSame(['Atelier', 'Cellar'], self::names($crawler));
        self::assertSelectorExists('tbody a[href="/shops/cellar"]');
    }

    public function testItFiltersAndSortsTheShops(): void
    {
        $client = static::createAdminClient();

        static::assertSame(['Atelier'], self::names($client->request('GET', '/shops', ['criteria' => ['search' => ['value' => 'atel']]])));
        static::assertSame(['Atelier'], self::names($client->request('GET', '/shops', ['criteria' => ['private' => 'true']])));
        static::assertSame(['Cellar'], self::names($client->request('GET', '/shops', ['criteria' => ['theme' => 'cellar']])));
        static::assertSame(['Cellar', 'Atelier'], self::names($client->request('GET', '/shops', ['sorting' => ['name' => 'desc']])));
    }

    public function testItOnlyLinksTheStorefrontOfAShopWithFixtures(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/shops');

        static::assertCount(1, $crawler->filter('tbody a[href="https://cellar.demo.test"]'));
        static::assertCount(0, $crawler->filter('tbody a[href="https://atelier.demo.test"]'));
        static::assertCount(1, $crawler->filter('tbody [title="Generate and load the fixtures first: the storefront is empty until then."]'));
    }

    public function testItShowsTheErrorOfCastorApiOnTheList(): void
    {
        FakeCastorApi::fail('sylius:shop:list', 'castor-api is down.');

        static::createAdminClient()->request('GET', '/shops');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'castor-api is down.');
    }

    public function testItShowsAShop(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/shops/cellar');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cellar');
        // Access of the demo customer.
        self::assertSelectorTextContains('body', 'cellar@shop.local');
        self::assertSelectorTextContains('body', 'shop-password');
        // Theme picker: every theme, the current one checked.
        static::assertSame(['default', 'canvas', 'cellar', 'drive'], $crawler->filter('input[name="theme"]')->each(static fn(Crawler $input): string => (string) $input->attr('value')));
        static::assertSame('cellar', $crawler->filter('input[name="theme"][checked]')->attr('value'));
        self::assertSelectorExists('img[src="/themes/canvas/preview-light.webp"]');
        // Header layout of the shop theme.
        static::assertSame(['inline', 'below', 'bar'], $crawler->filter('#header-menu option')->each(static fn(Crawler $option): string => (string) $option->attr('value')));
        self::assertSelectorTextContains('body', 'Belongs to the cellar theme');
    }

    public function testItHidesTheThemeCardsWhenCastorApiCannotListTheThemes(): void
    {
        FakeCastorApi::fail('sales-kit:theme:list', 'No theme.');

        static::createAdminClient()->request('GET', '/shops/cellar');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name="theme"]');
        self::assertSelectorNotExists('#header-menu');
    }

    public function testItSaysTheSyliusLookHasNoHeaderLayout(): void
    {
        static::createAdminClient()->request('GET', '/shops/atelier');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'The Sylius look has no header layout options');
        self::assertSelectorNotExists('#header-menu');
    }

    public function testItAnswersNotFoundForAnUnknownShop(): void
    {
        static::createAdminClient()->request('GET', '/shops/unknown');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return list<string>
     */
    private static function names(Crawler $crawler): array
    {
        return $crawler->filter('tbody tr td:first-child a[href^="/shops/"]')->each(static fn(Crawler $link): string => trim($link->text()));
    }
}
