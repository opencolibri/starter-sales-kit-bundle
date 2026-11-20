<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional;

use Symfony\Component\DomCrawler\Crawler;

final class DatasetTest extends WebTestCase
{
    public function testItListsTheDatasetsWithTheirThumbnail(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/datasets');

        self::assertResponseIsSuccessful();
        static::assertSame(['Cellar Wine Merchant', 'My catalog'], $crawler->filter('tbody tr td:first-child a.font-medium')->each(static fn(Crawler $link): string => trim($link->text())));
        self::assertSelectorExists('tbody img[src="/datasets/cellar/preview.webp"]');
        // A dataset built here has no thumbnail, but can be deleted and its missing images fetched.
        static::assertCount(1, $crawler->filter('tbody img'));
        static::assertCount(1, $crawler->filter('tbody form[action="/datasets/my-catalog/images"]'));
    }

    public function testItShowsTheCatalogOfAReadyMadeDataset(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/datasets/cellar');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cellar Wine Merchant');
        static::assertCount(17, $crawler->filter('main ul.grid > li'));
        // The shops created from it, and no delete button for a ready-made dataset.
        self::assertSelectorExists('a[href="/shops/cellar"]');
        static::assertStringNotContainsString('/datasets/cellar/delete', (string) static::getClient()?->getResponse()->getContent());
        self::assertSelectorExists('a[href="/shops/new?dataset=cellar"]');
    }

    public function testItShowsTheCatalogOfADatasetBuiltByTheStack(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/datasets/my-catalog');

        self::assertResponseIsSuccessful();
        static::assertSame(['Blue mug', 'Green mug', 'Dinner plate'], $crawler->filter('main ul.grid > li .font-medium')->each(static fn(Crawler $title): string => trim($title->text())));
        self::assertSelectorExists('img[src="/datasets/my-catalog/media/blue_mug.webp"]');
        self::assertSelectorTextContains('main ul.grid > li:last-child', 'No image yet');
        self::assertSelectorTextContains('main ul.grid > li:first-child', '12.50 €');
        self::assertSelectorTextContains('body', 'No shop was created from this dataset yet.');
        static::assertStringContainsString('/datasets/my-catalog/delete', (string) static::getClient()?->getResponse()->getContent());
    }

    public function testItFiltersTheCatalogByCollection(): void
    {
        $client = static::createAdminClient();

        $crawler = $client->request('GET', '/datasets/my-catalog', ['collection' => 'Mugs']);
        static::assertCount(2, $crawler->filter('main ul.grid > li'));
        self::assertSelectorTextContains('main nav[aria-label="Collections"] [aria-current="page"]', 'Mugs');

        // An unknown collection shows the whole catalog.
        static::assertCount(3, $client->request('GET', '/datasets/my-catalog', ['collection' => 'Bowls'])->filter('main ul.grid > li'));
    }

    public function testItServesTheMediaOfADataset(): void
    {
        $client = static::createAdminClient();

        $client->request('GET', '/datasets/my-catalog/media/blue_mug.webp');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'image/webp');

        $client->request('GET', '/datasets/my-catalog/media/missing.webp');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/datasets/my-catalog/media/products.yaml');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItAnswersNotFoundForAnUnknownDataset(): void
    {
        static::createAdminClient()->request('GET', '/datasets/unknown');

        self::assertResponseStatusCodeSame(404);
    }
}
