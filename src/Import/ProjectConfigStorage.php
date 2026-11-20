<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Import;

use Symfony\Component\Yaml\Yaml;

final class ProjectConfigStorage
{
    public function __construct(
        private readonly string $importVarDir,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $slug): ?array
    {
        $path = $this->path($slug);

        if (!is_file($path)) {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = Yaml::parseFile($path);

        return \is_array($data) ? $data : null;
    }

    /**
     * @param array{name?: string, description?: string, url?: string} $fields
     */
    public function updateSettings(string $slug, array $fields): void
    {
        $existing = $this->load($slug);

        if (null === $existing) {
            throw new \RuntimeException(\sprintf('Project "%s" not found.', $slug));
        }

        // Everything else the import tasks keep in project.yaml (passwords, dataset, shop target, theme, visibility…)
        // stays as it is.
        $config = array_replace($existing, [
            'slug' => $slug,
            'name' => trim((string) ($fields['name'] ?? $existing['name'] ?? '')),
            'description' => trim((string) ($fields['description'] ?? $existing['description'] ?? '')),
            'url' => trim((string) ($fields['url'] ?? $existing['url'] ?? '')),
            'shop_images' => $this->shopImagesPresence($slug),
        ]);

        $this->write($slug, $config);
    }

    public function refreshShopImagesFlags(string $slug): void
    {
        $existing = $this->load($slug);

        if (null === $existing) {
            return;
        }

        $existing['shop_images'] = $this->shopImagesPresence($slug);
        $this->write($slug, $existing);
    }

    /**
     * @return array{logo: bool, header: bool, interstice: bool}
     */
    private function shopImagesPresence(string $slug): array
    {
        $mediaDir = rtrim($this->importVarDir, '/\\') . '/' . $slug . '/media';

        return [
            'logo' => $this->hasRoleFile($mediaDir, 'image_logo'),
            'header' => $this->hasRoleFile($mediaDir, 'image_header'),
            'interstice' => $this->hasRoleFile($mediaDir, 'image_interstice'),
        ];
    }

    private function hasRoleFile(string $mediaDir, string $role): bool
    {
        if (!is_dir($mediaDir)) {
            return false;
        }

        foreach (glob($mediaDir . '/' . $role . '.*') ?: [] as $path) {
            if (is_file($path) && filesize($path) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function write(string $slug, array $config): void
    {
        $directory = rtrim($this->importVarDir, '/\\') . '/' . $slug;

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create shop config directory.');
        }

        $content = Yaml::dump($config, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        if (false === file_put_contents($this->path($slug), $content)) {
            throw new \RuntimeException('Could not write project config.');
        }
    }

    private function path(string $slug): string
    {
        return rtrim($this->importVarDir, '/\\') . '/' . $slug . '/project.yaml';
    }
}
