<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Dataset;

use OpenColibri\Starter\Datasets\DatasetPreview;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the catalog of a dataset from its files: the ready-made datasets from opencolibri/starter-datasets, the ones
 * built here from the .castor/import/datasets directory of the stack, mounted read only (import_datasets_dir).
 */
final readonly class DatasetCatalogReader
{
    private const SLUG = '/^[a-z0-9][a-z0-9_-]*$/';

    private const MEDIA = '/^[A-Za-z0-9][A-Za-z0-9._-]*\.(webp|png|jpe?g|gif|avif)$/';

    public function __construct(
        #[Autowire('%opencolibri_sales_kit.import_datasets_dir%')]
        private ?string $importDatasetsDir,
    ) {}

    public function read(string $slug): ?DatasetCatalog
    {
        $directory = $this->directory($slug);

        if (null === $directory) {
            return null;
        }

        try {
            $products = Yaml::parseFile($directory . '/products.yaml');
            $collections = is_file($directory . '/collections.yaml') ? Yaml::parseFile($directory . '/collections.yaml') : [];
        } catch (ParseException) {
            return null;
        }

        $items = [];
        $counts = [];

        foreach (\is_array($products['products'] ?? null) ? $products['products'] : [] as $product) {
            if (!\is_array($product) || !\is_string($product['title'] ?? null)) {
                continue;
            }

            $collection = \is_string($product['collection_name'] ?? null) ? $product['collection_name'] : null;
            $counts[$collection] = ($counts[$collection] ?? 0) + 1;
            $items[] = [
                'title' => $product['title'],
                'description' => \is_string($product['description'] ?? null) ? $product['description'] : null,
                'collection' => $collection,
                'price' => is_numeric($product['price_eur'] ?? null) ? (float) $product['price_eur'] : null,
                'image' => $this->media($directory, $product['image'] ?? null),
            ];
        }

        $groups = [];

        foreach (\is_array($collections['collections'] ?? null) ? $collections['collections'] : [] as $collection) {
            if (\is_array($collection) && \is_string($collection['name'] ?? null)) {
                $groups[] = [
                    'name' => $collection['name'],
                    'description' => \is_string($collection['description'] ?? null) ? $collection['description'] : null,
                    'image' => $this->media($directory, $collection['image'] ?? null),
                    'productCount' => $counts[$collection['name']] ?? 0,
                ];
            }
        }

        return new DatasetCatalog($groups, $items);
    }

    /**
     * @return string|null the file of a media of the dataset, or null when it does not exist
     */
    public function mediaPath(string $slug, string $file): ?string
    {
        $directory = $this->directory($slug);

        if (null === $directory || !preg_match(self::MEDIA, $file)) {
            return null;
        }

        $path = $directory . '/media/' . $file;

        return is_file($path) ? $path : null;
    }

    /**
     * Ready-made datasets first, as castor does: a dataset built here cannot take the slug of a ready-made one.
     */
    private function directory(string $slug): ?string
    {
        if (!preg_match(self::SLUG, $slug)) {
            return null;
        }

        foreach ([DatasetPreview::datasetsDir(), rtrim((string) $this->importDatasetsDir, '/\\')] as $root) {
            if ('' !== $root && is_file($root . '/' . $slug . '/products.yaml')) {
                return $root . '/' . $slug;
            }
        }

        return null;
    }

    /**
     * The file name of a downloaded image ("media/<file>"), or null for a remote one not downloaded yet.
     */
    private function media(string $directory, mixed $image): ?string
    {
        if (!\is_string($image) || !str_starts_with($image, 'media/')) {
            return null;
        }

        $file = substr($image, \strlen('media/'));

        return preg_match(self::MEDIA, $file) && is_file($directory . '/media/' . $file) ? $file : null;
    }
}
