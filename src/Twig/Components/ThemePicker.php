<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Twig\Components;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The storefront theme of one shop. Every theme is installed on the shared Sylius app, so picking one only
 * points the shop channel to it: no build, the storefront changes right away.
 */
#[AsLiveComponent]
final class ThemePicker
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /** Emitted with the "theme" once the shop uses it (HeaderLayoutPicker follows it). */
    public const APPLIED_EVENT = 'salesKit:themeApplied';

    #[LiveProp]
    public string $project = '';

    /** @var list<string> */
    #[LiveProp]
    public array $themes = [];

    #[LiveProp]
    public string $currentTheme = '';

    #[LiveProp(writable: true)]
    public string $theme = '';

    public ?string $error = null;

    public bool $applied = false;

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param list<string> $themes
     */
    public function mount(string $project, array $themes, string $current): void
    {
        $this->project = $project;
        $this->themes = $themes;
        $this->currentTheme = $current;
        $this->theme = $current;
    }

    #[LiveAction]
    public function apply(): void
    {
        if (!\in_array($this->theme, $this->themes, true)) {
            $this->error = \sprintf('Unknown theme "%s".', $this->theme);

            return;
        }

        try {
            $this->castorApi->run('sylius:shop:theme', ['shop' => $this->project, 'theme' => $this->theme]);
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->currentTheme = $this->theme;
        $this->applied = true;
        $this->emit(self::APPLIED_EVENT, ['theme' => $this->theme]);
    }
}
