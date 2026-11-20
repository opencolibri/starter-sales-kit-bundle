<?php

declare(strict_types=1);

namespace OpenColibri\Starter\SalesKitBundle\Tests\Functional\Component;

final class UiComponentsTest extends ComponentTestCase
{
    public function testTheHeaderPreviewDrawsTheMenuAndTheSearchWhereTheyGo(): void
    {
        $inline = $this->renderTwigComponent('sales_kit:header_preview', ['menu' => 'inline', 'search' => 'none'])->crawler();
        static::assertCount(0, $inline->filter('.border-primary\/70'));
        static::assertCount(0, $inline->filter('.bg-primary\/10'));

        $bar = $this->renderTwigComponent('sales_kit:header_preview', ['menu' => 'bar', 'search' => 'below'])->crawler();
        static::assertCount(1, $bar->filter('.bg-primary\/10'));
        static::assertCount(1, $bar->filter('.border-primary\/70.w-full'));

        $icon = $this->renderTwigComponent('sales_kit:header_preview', ['menu' => 'below', 'search' => 'icon'])->crawler();
        static::assertCount(1, $icon->filter('.border-primary\/70.absolute'));
        static::assertCount(1, $icon->filter('.border-t.mx-3'));
    }

    public function testTheHeaderPreviewPrintsItsIconsAsHtml(): void
    {
        $html = (string) $this->renderTwigComponent('sales_kit:header_preview', ['menu' => 'inline', 'search' => 'inline']);

        static::assertStringContainsString('<svg', $html);
        static::assertStringNotContainsString('&lt;svg', $html);
    }

    public function testTheActionButtonCallsTheLiveAction(): void
    {
        $button = $this->renderTwigComponent('sales_kit:action_button', ['action' => 'load', 'label' => 'Load', 'icon' => 'lucide:upload'])->crawler()->filter('button');

        static::assertSame('live#action', $button->attr('data-action'));
        static::assertSame('load', $button->attr('data-live-action-param'));
        static::assertStringNotContainsString('&lt;svg', $button->html());
    }

    public function testTheActionButtonAsksForAConfirmationFirst(): void
    {
        $html = (string) $this->renderTwigComponent('sales_kit:action_button', [
            'action' => 'delete',
            'label' => 'Delete',
            'variant' => 'destructive',
            'icon' => 'lucide:trash-2',
            'confirm' => 'Delete this shop?',
            'confirm_title' => 'Delete',
        ]);
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        $confirm = $crawler->filter('[data-slot="alert-dialog-footer"] button')->last();

        static::assertStringContainsString('Delete this shop?', $html);
        static::assertSame('shadcn--alert-dialog#close live#action', $confirm->attr('data-action'));
        static::assertSame('delete', $confirm->attr('data-live-action-param'));
        // The trigger only opens the dialog.
        static::assertCount(1, $crawler->filter('[data-live-action-param="delete"]'));
        static::assertStringNotContainsString('&lt;svg', $html);
    }
}
