<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional\Component;

use OpenColibri\Starter\SalesKitBundle\Tests\Application\Kernel;
use OpenColibri\Starter\SalesKitBundle\Tests\Support\FakeCastorApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

abstract class ComponentTestCase extends KernelTestCase
{
    use InteractsWithLiveComponents;
    use InteractsWithTwigComponents;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        FakeCastorApi::reset();
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function createAdminLiveComponent(string $name, array $data = []): TestLiveComponent
    {
        return $this->createLiveComponent($name, $data)->actingAs(new InMemoryUser('admin', 'admin', ['ROLE_USER']));
    }
}
