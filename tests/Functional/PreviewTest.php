<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional;

final class PreviewTest extends WebTestCase
{
    public function testItServesTheThumbnailsOfTheThemes(): void
    {
        $client = static::createAdminClient();

        foreach (['light', 'dark'] as $scheme) {
            $client->request('GET', '/themes/cellar/preview-' . $scheme . '.webp');
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('content-type', 'image/webp');
        }

        $client->request('GET', '/themes/unknown/preview-light.webp');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItServesTheThumbnailsOfTheReadyMadeDatasets(): void
    {
        $client = static::createAdminClient();

        $client->request('GET', '/datasets/cellar/preview.webp');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/datasets/my-catalog/preview.webp');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItKeepsTheThumbnailsForLoggedInUsers(): void
    {
        static::createClient()->request('GET', '/themes/cellar/preview-light.webp');

        self::assertResponseRedirects('/login');
    }
}
