<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Controller;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKitBundle\Form\ShopCreateType;
use OpenColibri\Starter\SalesKitBundle\Form\ShopSettingsType;
use OpenColibri\Starter\SalesKitBundle\Grid\ShopGrid;
use OpenColibri\Starter\SalesKitBundle\Import\ProjectConfigStorage;
use OpenColibri\Starter\SalesKitBundle\Import\ShopImageStorage;
use Sylius\Component\Grid\Parameters;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Sylius\Component\Grid\View\GridViewFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShopController extends AbstractController
{
    public function __construct(
        private readonly CastorApiClient $castorApi,
        private readonly ShopImageStorage $shopImageStorage,
        private readonly ProjectConfigStorage $projectConfigStorage,
        private readonly GridProviderInterface $gridProvider,
        private readonly GridViewFactoryInterface $gridViewFactory,
    ) {}

    #[Route('/shops', name: 'opencolibri_sales_kit_shop_index')]
    public function index(Request $request): Response
    {
        try {
            $resources = $this->gridViewFactory->create(
                $this->gridProvider->get(ShopGrid::NAME),
                new Parameters($request->query->all()),
            );
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
            $resources = null;
        }

        return $this->render('@OpenColibriStarterSalesKit/shop/index.html.twig', [
            'resources' => $resources,
        ]);
    }

    #[Route('/shops/new', name: 'opencolibri_sales_kit_shop_new')]
    public function new(Request $request): Response
    {
        try {
            $datasets = $this->castorApi->listDatasets();
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('opencolibri_sales_kit_shop_index');
        }

        if ([] === $datasets) {
            $this->addFlash('error', 'Create a dataset first: a shop is created from a dataset.');

            return $this->redirectToRoute('opencolibri_sales_kit_dataset_index');
        }

        $choices = [];

        foreach ($datasets as $dataset) {
            $choices[\sprintf('%s (%d products)', (string) ($dataset['name'] ?? ''), (int) ($dataset['productCount'] ?? 0))] = (string) ($dataset['slug'] ?? '');
        }

        $form = $this->createForm(ShopCreateType::class, ['dataset' => $request->query->getString('dataset') ?: null], ['datasets' => $choices]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{dataset: string, name: string, description: ?string} $data */
            $data = $form->getData();

            try {
                $slug = $this->shopImageStorage->slugFromName($data['name']);
                $this->castorApi->run('sylius:shop:create', [
                    'dataset' => $data['dataset'],
                    'shop' => $slug,
                    'name' => $data['name'],
                    'description' => (string) $data['description'],
                ]);
                $this->saveOptionalImages($slug, $form);
                $this->projectConfigStorage->refreshShopImagesFlags($slug);
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->redirectToRoute('opencolibri_sales_kit_shop_new', ['dataset' => $data['dataset']]);
            }

            $this->addFlash('success', 'Shop created: generate its fixtures, then load them into Sylius.');

            return $this->redirectToRoute('opencolibri_sales_kit_shop_show', ['slug' => $slug]);
        }

        return $this->render('@OpenColibriStarterSalesKit/shop/new.html.twig', [
            'form' => $form,
            'datasets' => $datasets,
        ]);
    }

    #[Route('/shops/{slug}', name: 'opencolibri_sales_kit_shop_show')]
    public function show(string $slug): Response
    {
        $shop = $this->findShop($slug);

        $projectForm = $this->createProjectForm($shop, $slug, [
            'action' => $this->generateUrl('opencolibri_sales_kit_shop_project', ['slug' => $slug]),
            'method' => 'POST',
        ]);

        return $this->render('@OpenColibriStarterSalesKit/shop/show.html.twig', [
            'shop' => $shop,
            'projectForm' => $projectForm,
            ...$this->themeCatalog(),
        ]);
    }

    #[Route('/shops/{slug}/project', name: 'opencolibri_sales_kit_shop_project', methods: ['POST'])]
    public function project(Request $request, string $slug): Response
    {
        $shop = $this->findShop($slug);

        $form = $this->createProjectForm($shop, $slug);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Invalid form submission.');

            return $this->redirectToRoute('opencolibri_sales_kit_shop_show', ['slug' => $slug]);
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();

        try {
            $this->saveOptionalImages($slug, $form);
            // A shop gets its catalog and URL from its dataset: only its name and description change.
            $this->projectConfigStorage->updateSettings($slug, array_diff_key($this->extractProjectSettings($data), ['url' => true]));
            $this->addFlash('success', 'Shop settings updated.');
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('opencolibri_sales_kit_shop_show', ['slug' => $slug]);
    }

    /**
     * A shop gets its catalog from its dataset: to change it, build the dataset again and create a shop from it.
     *
     * @param array<string, mixed> $shop
     * @param array<string, mixed> $options
     */
    private function createProjectForm(array $shop, string $slug, array $options = []): FormInterface
    {
        return $this->createForm(ShopSettingsType::class, [
            'name' => (string) ($shop['name'] ?? $slug),
            'description' => (string) ($shop['description'] ?? ''),
        ], array_merge(['csrf_token_id' => 'opencolibri_sales_kit_shop_project'], $options));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{name: string, description: string, url: string}
     */
    private function extractProjectSettings(array $data): array
    {
        return [
            'name' => (string) ($data['name'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'url' => (string) ($data['url'] ?? ''),
        ];
    }

    /**
     * The theme picker and the header layout are hidden when castor-api cannot list the themes.
     *
     * @return array{themes: list<string>|null, theme_headers: array<string, array{menu: string, search: string, menus: list<string>, searches: list<string>}>}
     */
    private function themeCatalog(): array
    {
        try {
            $catalog = $this->castorApi->themeCatalog();
        } catch (\RuntimeException) {
            return ['themes' => null, 'theme_headers' => []];
        }

        return ['themes' => $catalog['themes'], 'theme_headers' => $catalog['headers']];
    }

    /**
     * @return array<string, mixed>
     */
    private function findShop(string $slug): array
    {
        foreach ($this->castorApi->listShops() as $shop) {
            if (($shop['slug'] ?? null) === $slug) {
                return $shop;
            }
        }

        throw $this->createNotFoundException();
    }

    private function uploadedFile(mixed $value): ?UploadedFile
    {
        return $value instanceof UploadedFile ? $value : null;
    }

    private function saveOptionalImages(string $slug, FormInterface $form): void
    {
        $files = [
            'imageLogo' => $this->uploadedFile($form->get('imageLogo')->getData()),
            'imageHeader' => $this->uploadedFile($form->get('imageHeader')->getData()),
            'imageInterstice' => $this->uploadedFile($form->get('imageInterstice')->getData()),
        ];

        if (
            !$files['imageLogo'] instanceof UploadedFile
            && !$files['imageHeader'] instanceof UploadedFile
            && !$files['imageInterstice'] instanceof UploadedFile
        ) {
            return;
        }

        $this->shopImageStorage->save($slug, $files, requireLogo: false);
    }
}
