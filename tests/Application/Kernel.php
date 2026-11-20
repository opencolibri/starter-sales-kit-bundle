<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Application;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\SalesKitBundle\Tests\Support\FakeCastorApi;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * A sales kit instance for the tests: the bundle and its dependencies, an "admin" user, and castor-api replaced by
 * FakeCastorApi (no stack needed).
 */
final class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public const FIXTURES_DIR = __DIR__ . '/../Fixtures';

    public function registerBundles(): iterable
    {
        yield new \Symfony\Bundle\FrameworkBundle\FrameworkBundle();
        yield new \Symfony\Bundle\TwigBundle\TwigBundle();
        yield new \Symfony\Bundle\SecurityBundle\SecurityBundle();
        yield new \Symfony\UX\TwigComponent\TwigComponentBundle();
        yield new \Symfony\UX\StimulusBundle\StimulusBundle();
        yield new \Symfony\UX\LiveComponent\LiveComponentBundle();
        yield new \Symfony\UX\Icons\UXIconsBundle();
        yield new \Twig\Extra\TwigExtraBundle\TwigExtraBundle();
        yield new \Symfonycasts\TailwindBundle\SymfonycastsTailwindBundle();
        yield new \TalesFromADev\Twig\Extra\Tailwind\Bridge\Symfony\Bundle\TalesFromADevTwigExtraTailwindBundle();
        yield new \Knp\Bundle\MenuBundle\KnpMenuBundle();
        yield new \BabDev\PagerfantaBundle\BabDevPagerfantaBundle();
        yield new \Sylius\Bundle\ResourceBundle\SyliusResourceBundle();
        yield new \Sylius\Bundle\GridBundle\SyliusGridBundle();
        yield new \Sylius\TwigHooks\SyliusTwigHooksBundle();
        yield new \Sylius\TwigExtra\Symfony\SyliusTwigExtraBundle();
        yield new \Sylius\UiTranslations\Symfony\SyliusUiTranslationsBundle();
        yield new \Sylius\AdminUi\Symfony\SyliusAdminUiBundle();
        yield new \Sylius\ShadcnAdminUi\Symfony\SyliusShadcnAdminUiBundle();
        yield new \OpenColibri\Starter\SalesKitBundle\OpenColibriStarterSalesKitBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/opencolibri-sales-kit-tests/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/opencolibri-sales-kit-tests/log';
    }

    public function process(ContainerBuilder $container): void
    {
        // Every castor-api call goes to FakeCastorApi.
        $container->getDefinition(CastorApiClient::class)->setArgument('$httpClient', new Reference('test.castor_api.http_client'));
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'form' => ['csrf_protection' => ['enabled' => true]],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'asset_mapper' => ['paths' => ['assets/']],
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['default_path' => '%kernel.project_dir%/templates']);
        $container->extension('symfonycasts_tailwind', ['input_css' => ['%kernel.project_dir%/assets/admin.css']]);
        $container->extension('security', [
            'password_hashers' => ['Symfony\Component\Security\Core\User\InMemoryUser' => 'plaintext'],
            'providers' => ['admin' => ['memory' => ['users' => ['admin' => ['password' => 'admin', 'roles' => ['ROLE_USER']]]]]],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'admin',
                    'form_login' => [
                        'login_path' => 'sylius_admin_ui_login',
                        'check_path' => 'sylius_admin_ui_login_check',
                        'default_target_path' => 'sylius_admin_ui_dashboard',
                        'enable_csrf' => true,
                        'csrf_parameter' => '_csrf_admin_security_token',
                        'csrf_token_id' => 'admin_authenticate',
                    ],
                    'logout' => ['path' => 'sylius_admin_ui_logout', 'target' => 'sylius_admin_ui_login'],
                ],
            ],
            'access_control' => [
                ['path' => '^/_caddy/on-demand-ask', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ]);

        $container->import('@OpenColibriStarterSalesKitBundle/config/app.php');
        $container->extension('opencolibri_sales_kit', [
            'castor_api' => ['url' => 'http://castor-api.test', 'token' => 'secret-token'],
            'shop_root_domain' => 'demo.test',
            'import_var_dir' => self::FIXTURES_DIR . '/import-var',
            'import_export_dir' => self::FIXTURES_DIR . '/import-exports',
            'import_datasets_dir' => self::FIXTURES_DIR . '/import-datasets',
        ]);

        $container->services()
            // The default logger writes to stderr.
            ->set('logger', NullLogger::class)
            ->set('test.castor_api', FakeCastorApi::class)->public()
            ->set('test.castor_api.http_client', MockHttpClient::class)->args([new Reference('test.castor_api'), 'http://castor-api.test'])
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@SyliusAdminUiBundle/config/routes.php');
        $routes->import('@OpenColibriStarterSalesKitBundle/config/routes.php');
        $routes->import('@LiveComponentBundle/config/routes.php')->prefix('/_components');
        $routes->import('security.route_loader.logout', 'service');
    }
}
