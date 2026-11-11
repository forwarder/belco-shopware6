<?php declare(strict_types=1);

namespace BelcoShopware\Tests\Storefront\Subscriber;

use BelcoShopware\Storefront\Subscriber\BelcoSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Event\StorefrontRenderEvent;

final class BelcoSubscriberTest extends TestCase
{
    public function testWidgetConfigurationIsAddedToStorefrontRenderContext(): void
    {
        self::assertSame(
            [StorefrontRenderEvent::class => 'onStorefrontRender'],
            BelcoSubscriber::getSubscribedEvents()
        );
    }

    public function testBaseTemplateDoesNotDependOnFooterPageletData(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 3) . '/src/Resources/views/storefront/base.html.twig'
        );

        self::assertNotFalse($template);
        self::assertStringContainsString('belcoConfig is defined', $template);
        self::assertStringContainsString('window.belcoConfig = {{ belcoConfig|raw }}', $template);
        self::assertStringNotContainsString('page.footer', $template);
    }
}
