<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Twig\Components;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Figures and latest shops of the dashboard. castor-api errors are shown in the component, not thrown.
 */
#[AsTwigComponent]
final class DashboardStats
{
    public ?string $error = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $shops = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $datasets = null;

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function getShops(): array
    {
        return $this->shops ??= $this->fetch($this->castorApi->listShops(...));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDatasets(): array
    {
        return $this->datasets ??= $this->fetch($this->castorApi->listDatasets(...));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLatestShops(): array
    {
        $shops = $this->getShops();
        usort($shops, static fn(array $a, array $b): int => strcmp((string) ($b['importedAt'] ?? ''), (string) ($a['importedAt'] ?? '')));

        return \array_slice($shops, 0, 5);
    }

    public function countLoadedShops(): int
    {
        return \count(array_filter($this->getShops(), static fn(array $shop): bool => (bool) ($shop['hasFixtures'] ?? false)));
    }

    public function countPrivateShops(): int
    {
        return \count(array_filter($this->getShops(), static fn(array $shop): bool => (bool) ($shop['private'] ?? false)));
    }

    /**
     * @param callable(): list<array<string, mixed>> $list
     *
     * @return list<array<string, mixed>>
     */
    private function fetch(callable $list): array
    {
        try {
            return $list();
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return [];
        }
    }
}
