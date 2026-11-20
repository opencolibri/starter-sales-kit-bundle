<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional;

final class CaddyOnDemandAskTest extends WebTestCase
{
    public function testItAllowsTheDomainsOfTheSalesKitOnly(): void
    {
        $client = static::createClient();

        foreach (['admin.demo.test' => 200, 'cellar.demo.test' => 200, 'CELLAR.demo.test.' => 200, 'demo.test' => 403, 'a.b.demo.test' => 403, 'example.com' => 403, '' => 400] as $domain => $status) {
            $client->request('GET', '/_caddy/on-demand-ask', ['domain' => $domain]);

            self::assertResponseStatusCodeSame($status, \sprintf('Domain "%s".', $domain));
        }
    }
}
