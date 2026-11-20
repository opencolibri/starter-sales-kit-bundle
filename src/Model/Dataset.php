<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Model;

/**
 * A catalog shops are created from, as listed by castor-api ("sylius:dataset:list"): one row of the dataset grid.
 */
final readonly class Dataset
{
    public const SOURCE_READY_MADE = 'ready-made';

    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description = null,
        public ?string $theme = null,
        public bool $builtIn = false,
        public ?string $source = null,
        public ?string $url = null,
        public int $productCount = 0,
        public int $imageCount = 0,
        public int $collectionCount = 0,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $slug = (string) ($data['slug'] ?? '');
        $string = static fn(mixed $value): ?string => \is_scalar($value) && '' !== (string) $value ? (string) $value : null;

        return new self(
            slug: $slug,
            name: (string) ($data['name'] ?? $slug),
            description: $string($data['description'] ?? null),
            theme: $string($data['theme'] ?? null),
            builtIn: (bool) ($data['builtIn'] ?? false),
            source: $string($data['source'] ?? null),
            url: $string($data['url'] ?? null),
            productCount: (int) ($data['productCount'] ?? 0),
            imageCount: (int) ($data['imageCount'] ?? 0),
            collectionCount: (int) ($data['collectionCount'] ?? 0),
        );
    }

    /**
     * "ready-made" for the datasets of opencolibri/starter-datasets, else the source it was built from ("ai", "existing").
     */
    public function getOrigin(): string
    {
        return $this->builtIn ? self::SOURCE_READY_MADE : ($this->source ?? '');
    }

    public function hasMissingImages(): bool
    {
        return !$this->builtIn && $this->imageCount < $this->productCount;
    }
}
