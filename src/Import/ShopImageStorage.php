<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ShopImageStorage
{
    public const MAX_BYTES = 5_242_880;

    /** @var array<string, string> */
    public const FIELD_ROLES = [
        'imageLogo' => 'image_logo',
        'imageHeader' => 'image_header',
        'imageInterstice' => 'image_interstice',
    ];

    public function __construct(
        private readonly string $importVarDir,
    ) {}

    public function slugFromName(string $name): string
    {
        $name = trim($name);

        if ('' === $name) {
            throw new \InvalidArgumentException('Project name is required.');
        }

        $normalized = mb_strtolower($name);

        if (\function_exists('transliterator_transliterate')) {
            $normalized = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $normalized) ?: $normalized;
        } elseif (\function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
            $normalized = false !== $converted ? strtolower($converted) : $normalized;
        }

        $normalized = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $slug = trim($normalized, '-');

        if ('' === $slug) {
            throw new \InvalidArgumentException('Project name must contain at least one alphanumeric character.');
        }

        return $slug;
    }

    /**
     * @param array<string, UploadedFile|null> $files
     */
    public function save(string $slug, array $files, bool $requireLogo = true): void
    {
        $logo = $files['imageLogo'] ?? null;

        if ($requireLogo && (!$logo instanceof UploadedFile || !$logo->isValid())) {
            throw new \RuntimeException('Logo upload is required.');
        }

        if (!$requireLogo && $logo instanceof UploadedFile && !$logo->isValid()) {
            throw new \RuntimeException('Could not save "imageLogo" (invalid upload).');
        }

        foreach (self::FIELD_ROLES as $field => $role) {
            $file = $files[$field] ?? null;

            if (!$file instanceof UploadedFile) {
                continue;
            }

            if (!$file->isValid()) {
                throw new \RuntimeException(\sprintf('Could not save "%s" (invalid upload).', $field));
            }

            $this->write($slug, $role, $file);
        }
    }

    private function write(string $slug, string $role, UploadedFile $file): void
    {
        $binary = file_get_contents($file->getPathname());

        if (false === $binary || '' === $binary || \strlen($binary) > self::MAX_BYTES) {
            throw new \RuntimeException(\sprintf('Could not save "%s" (invalid format or size).', $role));
        }

        $mimeType = $this->normalizeMimeType((string) $file->getClientMimeType(), $binary);

        if (null === $mimeType) {
            throw new \RuntimeException(\sprintf('Could not save "%s" (invalid format or size).', $role));
        }

        $directory = rtrim($this->importVarDir, '/\\') . '/' . $slug . '/media';

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create shop media directory.');
        }

        foreach (glob($directory . '/' . $role . '.*') ?: [] as $existingPath) {
            if (is_file($existingPath)) {
                unlink($existingPath);
            }
        }

        $path = $directory . '/' . $role . '.' . $this->extensionFromMimeType($mimeType);

        if (false === file_put_contents($path, $binary) || !is_file($path) || 0 === filesize($path)) {
            throw new \RuntimeException(\sprintf('Could not save "%s" (invalid format or size).', $role));
        }
    }

    private function normalizeMimeType(string $mimeType, string $binary): ?string
    {
        $mimeType = strtolower(trim(explode(';', $mimeType)[0]));

        if (\in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return $mimeType;
        }

        if (\function_exists('finfo_open')) {
            $finfo = finfo_open(\FILEINFO_MIME_TYPE);

            if (false !== $finfo) {
                $detected = finfo_buffer($finfo, $binary);
                finfo_close($finfo);

                if (\is_string($detected) && \in_array($detected, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                    return $detected;
                }
            }
        }

        return null;
    }

    private function extensionFromMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
