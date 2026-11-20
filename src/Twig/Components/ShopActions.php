<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Twig\Components;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Generates and loads the fixtures of a shop. The runs are followed by the RunMonitor component of the page.
 */
#[AsLiveComponent]
final class ShopActions
{
    use DefaultActionTrait;
    use StartsRunTrait;

    #[LiveProp]
    public string $slug = '';

    #[LiveProp]
    public string $mode = 'existing';

    #[LiveProp]
    public bool $hasProducts = false;

    #[LiveProp]
    public bool $hasFixtures = false;

    #[LiveProp(writable: true)]
    public int $limit = 100;

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param array<string, mixed> $shop
     */
    public function mount(array $shop): void
    {
        $this->slug = (string) ($shop['slug'] ?? '');
        $this->mode = 'ai' === ($shop['mode'] ?? 'existing') ? 'ai' : 'existing';
        $this->hasProducts = (bool) ($shop['hasProducts'] ?? false);
        $this->hasFixtures = (bool) ($shop['hasFixtures'] ?? false);
    }

    #[LiveAction]
    public function generate(): void
    {
        if (!$this->hasProducts) {
            $this->error = 'Fixtures cannot be generated until products have been imported. Re-fetch the site first.';

            return;
        }

        $payload = [
            'shop' => $this->slug,
            'subdomain' => $this->slug,
        ];

        if ('ai' !== $this->mode) {
            if ($this->limit < 1 || $this->limit > 500) {
                $this->error = 'Max products to include must be between 1 and 500.';

                return;
            }

            $payload['limit'] = $this->limit;
        }

        $this->startRun('sylius:shop:generate', $payload);
    }

    #[LiveAction]
    public function load(): void
    {
        if (!$this->hasFixtures) {
            $this->error = 'Generate fixtures before loading them into Sylius.';

            return;
        }

        $this->startRun('sylius:shop:load', ['shop' => $this->slug]);
    }

    public function isAi(): bool
    {
        return 'ai' === $this->mode;
    }
}
