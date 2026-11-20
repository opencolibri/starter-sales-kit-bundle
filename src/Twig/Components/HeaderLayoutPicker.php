<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Twig\Components;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Where the storefront header shows the category menu and the search. The header layout belongs to the theme, not to
 * the shop: every theme is installed once on the shared Sylius app, so changing it changes every shop on that theme.
 * Follows the theme picked by ThemePicker on the same page.
 */
#[AsLiveComponent]
final class HeaderLayoutPicker
{
    use DefaultActionTrait;

    /**
     * Header layout of each installed theme, as listed by castor-api.
     *
     * @var array<string, array{menu: string, search: string, menus: list<string>, searches: list<string>}>
     */
    #[LiveProp]
    public array $headers = [];

    #[LiveProp]
    public string $theme = '';

    #[LiveProp(writable: true)]
    public string $menu = '';

    #[LiveProp(writable: true)]
    public string $search = '';

    public ?string $error = null;

    public bool $applied = false;

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param array<string, array{menu: string, search: string, menus: list<string>, searches: list<string>}> $headers
     */
    public function mount(array $headers, string $theme): void
    {
        $this->headers = $headers;
        $this->selectTheme($theme);
    }

    /**
     * @return array{menu: string, search: string, menus: list<string>, searches: list<string>}|null
     */
    public function getHeader(): ?array
    {
        return $this->headers[$this->theme] ?? null;
    }

    #[LiveListener(ThemePicker::APPLIED_EVENT)]
    public function onThemeApplied(#[LiveArg] string $theme): void
    {
        $this->selectTheme($theme);
        $this->applied = false;
    }

    #[LiveAction]
    public function apply(): void
    {
        $header = $this->getHeader();

        if (null === $header) {
            $this->error = \sprintf('The "%s" theme has no header layout.', $this->theme);

            return;
        }

        if (!\in_array($this->menu, $header['menus'], true) || !\in_array($this->search, $header['searches'], true)) {
            $this->error = \sprintf('The "%s" theme does not support this header layout.', $this->theme);

            return;
        }

        try {
            $this->castorApi->run('sales-kit:theme:header', ['theme' => $this->theme, 'menu' => $this->menu, 'search' => $this->search]);
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->headers[$this->theme]['menu'] = $this->menu;
        $this->headers[$this->theme]['search'] = $this->search;
        $this->applied = true;
    }

    private function selectTheme(string $theme): void
    {
        $this->theme = $theme;
        $this->menu = $this->headers[$theme]['menu'] ?? '';
        $this->search = $this->headers[$theme]['search'] ?? '';
    }
}
