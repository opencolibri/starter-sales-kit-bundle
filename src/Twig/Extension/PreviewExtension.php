<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Twig\Extension;

use OpenColibri\Starter\Datasets\DatasetPreview;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class PreviewExtension
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * The thumbnail URL of a ready-made dataset, or null for a dataset built in this sales kit.
     */
    #[AsTwigFunction('sales_kit_dataset_preview')]
    public function datasetPreview(string $dataset): ?string
    {
        return null !== DatasetPreview::path($dataset) ? $this->urlGenerator->generate('opencolibri_sales_kit_dataset_preview', ['dataset' => $dataset]) : null;
    }
}
