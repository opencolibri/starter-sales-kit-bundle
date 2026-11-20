<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lets Encrypt on-demand TLS ask endpoint (called by the global Castor Caddy router).
 * Allows admin.{shopRootDomain} and {slug}.{shopRootDomain} (wildcard app labels + on-demand TLS).
 */
final class CaddyOnDemandAskController extends AbstractController
{
    #[Route('/_caddy/on-demand-ask', name: 'opencolibri_sales_kit_caddy_on_demand_ask', methods: ['GET'])]
    public function __invoke(
        Request $request,
        #[Autowire('%opencolibri_sales_kit.shop_root_domain%')]
        string $shopRootDomain,
    ): Response {
        $domain = $request->query->get('domain', '');

        if ('' === $domain) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $domain = strtolower(rtrim($domain, '.'));

        if ($this->isAllowedOnDemandHostname($domain, $shopRootDomain)) {
            return new Response('', Response::HTTP_OK);
        }

        return new Response('', Response::HTTP_FORBIDDEN);
    }

    private function isAllowedOnDemandHostname(string $domain, string $shopRootDomain): bool
    {
        $shopRootDomain = strtolower($shopRootDomain);

        if ($domain === $shopRootDomain) {
            return false;
        }

        $suffix = '.' . $shopRootDomain;

        if (!str_ends_with($domain, $suffix)) {
            return false;
        }

        $prefix = substr($domain, 0, -\strlen($suffix));

        if ('' === $prefix || str_contains($prefix, '.')) {
            return false;
        }

        return 1 === preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $prefix);
    }
}
