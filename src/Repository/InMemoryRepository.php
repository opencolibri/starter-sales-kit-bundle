<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Repository;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Immutable query over a list loaded once per request (castor-api returns every item in one call): each "with*()"
 * or "orderBy()" returns a filtered copy, like a Doctrine repository wrapping a query builder.
 *
 * @template T of object
 */
abstract class InMemoryRepository implements ResetInterface
{
    /** @var list<callable(T): bool> */
    private array $filters = [];

    /** @var list<array{callable(T): mixed, string}> */
    private array $orderings = [];

    /** Shared by the copies: the items are loaded once. */
    private \ArrayObject $cache;

    public function __construct()
    {
        $this->cache = new \ArrayObject();
    }

    /**
     * @return list<T>
     */
    abstract protected function load(): array;

    /**
     * @return list<T>
     */
    public function findAll(): array
    {
        if (!isset($this->cache['items'])) {
            $this->cache['items'] = $this->load();
        }

        return $this->cache['items'];
    }

    /** Forgets the loaded items between two requests (long-running workers). */
    public function reset(): void
    {
        $this->cache->exchangeArray([]);
    }

    /**
     * @return list<T>
     */
    public function getResult(): array
    {
        $items = $this->findAll();

        foreach ($this->filters as $filter) {
            $items = array_values(array_filter($items, $filter));
        }

        if ([] !== $this->orderings) {
            usort($items, function (object $a, object $b): int {
                foreach ($this->orderings as [$value, $direction]) {
                    $result = self::compare($value($a), $value($b));

                    if (0 !== $result) {
                        return 'desc' === $direction ? -$result : $result;
                    }
                }

                return 0;
            });
        }

        return $items;
    }

    /**
     * @return PagerfantaInterface<T>
     */
    public function paginate(int $page, int $itemsPerPage): PagerfantaInterface
    {
        $paginator = new Pagerfanta(new ArrayAdapter($this->getResult()));
        $paginator->setNormalizeOutOfRangePages(true);
        $paginator->setMaxPerPage(max(1, $itemsPerPage));
        $paginator->setCurrentPage(max(1, $page));

        return $paginator;
    }

    /**
     * @param callable(T): bool $filter
     */
    protected function filter(callable $filter): static
    {
        $copy = clone $this;
        $copy->filters[] = $filter;

        return $copy;
    }

    /**
     * @param callable(T): mixed $value
     */
    protected function sortBy(callable $value, string $direction): static
    {
        $copy = clone $this;
        $copy->orderings[] = [$value, 'desc' === strtolower($direction) ? 'desc' : 'asc'];

        return $copy;
    }

    protected static function contains(?string $haystack, string $needle): bool
    {
        return null !== $haystack && str_contains(mb_strtolower($haystack), mb_strtolower($needle));
    }

    private static function compare(mixed $a, mixed $b): int
    {
        if (\is_string($a) || \is_string($b)) {
            return strnatcasecmp((string) $a, (string) $b);
        }

        return $a <=> $b;
    }
}
