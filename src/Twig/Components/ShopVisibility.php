<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Twig\Components;

use OpenColibri\Starter\SalesKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Whether the storefront of one shop is public or kept to its demo customer, who logs in with the storefront
 * access of the shop. Applies to the shop channel right away.
 */
#[AsLiveComponent]
final class ShopVisibility
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $project = '';

    #[LiveProp]
    public bool $private = false;

    public ?string $error = null;

    public bool $applied = false;

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    #[LiveAction]
    public function makePrivate(): void
    {
        $this->apply(true);
    }

    #[LiveAction]
    public function makePublic(): void
    {
        $this->apply(false);
    }

    private function apply(bool $private): void
    {
        try {
            $this->castorApi->run('sylius:shop:visibility', ['shop' => $this->project, 'visibility' => $private ? 'private' : 'public']);
        } catch (\RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->private = $private;
        $this->applied = true;
    }
}
