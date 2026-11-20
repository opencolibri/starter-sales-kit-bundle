<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Model;

/**
 * A demo shop, as listed by castor-api ("sylius:shop:list"): one row of the shop grid.
 */
final readonly class Shop
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $dataset = null,
        public string $theme = 'default',
        public ?int $productCount = null,
        public bool $hasYaml = false,
        public bool $hasFixtures = false,
        public bool $private = false,
        public ?string $channelCode = null,
        public ?string $importedAt = null,
        public ?string $shopUrl = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $slug = (string) ($data['slug'] ?? '');

        return new self(
            slug: $slug,
            name: (string) ($data['name'] ?? $slug),
            dataset: self::stringOrNull($data['dataset'] ?? null),
            theme: self::stringOrNull($data['theme'] ?? null) ?? 'default',
            productCount: isset($data['productCount']) ? (int) $data['productCount'] : null,
            hasYaml: (bool) ($data['hasYaml'] ?? false),
            hasFixtures: (bool) ($data['hasFixtures'] ?? false),
            private: (bool) ($data['private'] ?? false),
            channelCode: self::stringOrNull($data['channelCode'] ?? null),
            importedAt: self::stringOrNull($data['importedAt'] ?? null),
            shopUrl: self::stringOrNull($data['shopUrl'] ?? null),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }
}
