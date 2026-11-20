<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Controller;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKitBundle\Dataset\DatasetCatalogReader;
use OpenColibri\Starter\SalesKitBundle\Form\DatasetAiType;
use OpenColibri\Starter\SalesKitBundle\Form\DatasetExistingType;
use OpenColibri\Starter\SalesKitBundle\Grid\DatasetGrid;
use OpenColibri\Starter\SalesKitBundle\Import\ShopImageStorage;
use OpenColibri\Starter\SalesKitBundle\Model\Dataset;
use OpenColibri\Starter\SalesKitBundle\Repository\DatasetRepository;
use OpenColibri\Starter\SalesKitBundle\Repository\ShopRepository;
use Sylius\Component\Grid\Parameters;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Sylius\Component\Grid\View\GridViewFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Datasets are the catalogs (products, collections and their images) shops are created from: ready-made ones from
 * opencolibri/starter-datasets, and the ones built here from an AI description or an existing site.
 */
final class DatasetController extends AbstractController
{
    public function __construct(
        private readonly CastorApiClient $castorApi,
        private readonly ShopImageStorage $shopImageStorage,
        #[Autowire('%opencolibri_sales_kit.import_export_dir%')]
        private readonly string $exportDir,
        private readonly GridProviderInterface $gridProvider,
        private readonly GridViewFactoryInterface $gridViewFactory,
        private readonly DatasetRepository $datasets,
        private readonly ShopRepository $shops,
        private readonly DatasetCatalogReader $catalogReader,
    ) {}

    #[Route('/datasets', name: 'opencolibri_sales_kit_dataset_index')]
    public function index(Request $request): Response
    {
        try {
            $resources = $this->gridViewFactory->create(
                $this->gridProvider->get(DatasetGrid::NAME),
                new Parameters($request->query->all()),
            );
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
            $resources = null;
        }

        return $this->render('@OpenColibriStarterSalesKit/dataset/index.html.twig', [
            'resources' => $resources,
        ]);
    }

    #[Route('/datasets/new/{source}', name: 'opencolibri_sales_kit_dataset_new', requirements: ['source' => 'existing|ai'])]
    public function new(Request $request, string $source): Response
    {
        $form = $this->createForm('ai' === $source ? DatasetAiType::class : DatasetExistingType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();

            try {
                $started = $this->castorApi->start('sylius:dataset:build', [
                    'source' => $source,
                    'dataset' => $this->shopImageStorage->slugFromName((string) ($data['name'] ?? '')),
                    'name' => (string) ($data['name'] ?? ''),
                    'description' => (string) ($data['description'] ?? ''),
                    'url' => (string) ($data['url'] ?? ''),
                    'limit' => (int) ($data['limit'] ?? 20),
                ]);
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->redirectToRoute('opencolibri_sales_kit_dataset_new', ['source' => $source]);
            }

            return $this->redirectToRoute('opencolibri_sales_kit_dataset_index', [
                'run' => $started['id'],
                'task' => $started['task'],
            ]);
        }

        return $this->render('@OpenColibriStarterSalesKit/dataset/new.html.twig', [
            'source' => $source,
            'form' => $form,
        ]);
    }

    #[Route('/datasets/{slug}', name: 'opencolibri_sales_kit_dataset_show', requirements: ['slug' => '[a-z0-9][a-z0-9_-]*'], methods: ['GET'])]
    public function show(Request $request, string $slug): Response
    {
        $dataset = $this->findDataset($slug);
        $catalog = $this->catalogReader->read($slug);
        $collection = $request->query->getString('collection');
        $collection = null !== $catalog && \in_array($collection, array_column($catalog->collections, 'name'), true) ? $collection : null;

        return $this->render('@OpenColibriStarterSalesKit/dataset/show.html.twig', [
            'dataset' => $dataset,
            'catalog' => $catalog,
            'collection' => $collection,
            'shops' => $this->shops->withDataset($slug)->orderBy('name')->getResult(),
        ]);
    }

    #[Route('/datasets/{slug}/media/{file}', name: 'opencolibri_sales_kit_dataset_media', requirements: ['slug' => '[a-z0-9][a-z0-9_-]*', 'file' => '[A-Za-z0-9][A-Za-z0-9._-]*'], methods: ['GET'])]
    public function media(string $slug, string $file): BinaryFileResponse
    {
        $path = $this->catalogReader->mediaPath($slug, $file) ?? throw $this->createNotFoundException();

        $response = new BinaryFileResponse($path, autoEtag: true, autoLastModified: true);
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    #[Route('/datasets/{slug}/download', name: 'opencolibri_sales_kit_dataset_download', methods: ['GET'])]
    public function download(string $slug): Response
    {
        $this->assertSlug($slug);

        try {
            $this->castorApi->run('sylius:dataset:export', ['dataset' => $slug]);
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('opencolibri_sales_kit_dataset_index');
        }

        $path = rtrim($this->exportDir, '/\\') . '/' . $slug . '.zip';

        if (!is_file($path)) {
            $this->addFlash('error', \sprintf('The export of dataset "%s" was not found in %s.', $slug, $this->exportDir));

            return $this->redirectToRoute('opencolibri_sales_kit_dataset_index');
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $slug . '.zip');

        return $response;
    }

    #[Route('/datasets/{slug}/images', name: 'opencolibri_sales_kit_dataset_images', methods: ['POST'])]
    public function images(Request $request, string $slug): Response
    {
        $this->assertSlug($slug);

        if (!$this->isCsrfTokenValid('dataset_images_' . $slug, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Invalid form submission.');

            return $this->redirectToRoute('opencolibri_sales_kit_dataset_index');
        }

        try {
            $started = $this->castorApi->start('sylius:dataset:images', ['dataset' => $slug]);
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('opencolibri_sales_kit_dataset_index');
        }

        return $this->redirectToRoute('opencolibri_sales_kit_dataset_index', [
            'run' => $started['id'],
            'task' => $started['task'],
        ]);
    }

    #[Route('/datasets/{slug}/delete', name: 'opencolibri_sales_kit_dataset_delete', methods: ['POST'])]
    public function delete(Request $request, string $slug): Response
    {
        $this->assertSlug($slug);

        // Sent by the sylius_shadcn_admin_ui:delete_modal component.
        if (!$this->isCsrfTokenValid('dataset_delete_' . $slug, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Invalid form submission.');

            return $this->redirectToRoute('opencolibri_sales_kit_dataset_index');
        }

        try {
            $this->castorApi->run('sylius:dataset:delete', ['dataset' => $slug]);
            $this->addFlash('success', \sprintf('Dataset "%s" deleted. The shops created from it are kept.', $slug));
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('opencolibri_sales_kit_dataset_index');
    }

    private function findDataset(string $slug): Dataset
    {
        foreach ($this->datasets->findAll() as $dataset) {
            if ($dataset->slug === $slug) {
                return $dataset;
            }
        }

        throw $this->createNotFoundException(\sprintf('Unknown dataset "%s".', $slug));
    }

    private function assertSlug(string $slug): void
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
            throw $this->createNotFoundException();
        }
    }
}
