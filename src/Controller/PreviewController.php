<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Controller;

use OpenColibri\Starter\Datasets\DatasetPreview;
use OpenColibri\Starter\Themes\ThemePreview;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The thumbnails of the storefront themes (opencolibri/starter-themes) and of the ready-made datasets
 * (opencolibri/starter-datasets), for the theme picker and the dataset list.
 */
final class PreviewController
{
    #[Route('/themes/{theme}/preview-{scheme}.webp', name: 'opencolibri_sales_kit_theme_preview', requirements: ['theme' => '[a-z0-9_]+', 'scheme' => 'light|dark'], methods: ['GET'])]
    public function theme(string $theme, string $scheme): BinaryFileResponse
    {
        return self::image(ThemePreview::path($theme, $scheme) ?? throw new NotFoundHttpException(\sprintf('The "%s" theme has no preview.', $theme)));
    }

    #[Route('/datasets/{dataset}/preview.webp', name: 'opencolibri_sales_kit_dataset_preview', requirements: ['dataset' => '[a-z0-9][a-z0-9_-]*'], methods: ['GET'])]
    public function dataset(string $dataset): BinaryFileResponse
    {
        return self::image(DatasetPreview::path($dataset) ?? throw new NotFoundHttpException(\sprintf('The "%s" dataset has no preview.', $dataset)));
    }

    private static function image(string $path): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp'], autoEtag: true, autoLastModified: true);
        $response->setPrivate();
        $response->setMaxAge(86400);

        return $response;
    }
}
