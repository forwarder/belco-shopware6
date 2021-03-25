<?php declare(strict_types=1);

namespace BelcoShopware\Storefront\Subscriber;

use JetBrains\PhpStorm\ArrayShape;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Class BelcoSubscriber
 * @package BelcoShopware\Storefront\Subscriber
 */
class BelcoSubscriber implements EventSubscriberInterface
{
    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrl;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        if ($context->keepUserData()) {
            return; //NOSONAR
        }
    }

    /**
     * @return string[]
     */
    #[ArrayShape([FooterPageletLoadedEvent::class => "string"])]
    public static function getSubscribedEvents(): array
    {
        return [
            FooterPageletLoadedEvent::class => 'onPostDispatch'
        ];
    }

    /**
     * BelcoSubscriber constructor.
     * @param SystemConfigService $systemConfigService
     * @param CartService $cartService
     * @param SeoUrlPlaceholderHandlerInterface $ceoUrl
     * @param EntityRepositoryInterface $entityRepository
     */
    public function __construct(SystemConfigService $systemConfigService,
                                CartService $cartService,
                                SeoUrlPlaceholderHandlerInterface $ceoUrl,
                                EntityRepositoryInterface $entityRepository
    )
    {
        $this->systemConfigService = $systemConfigService;
        $this->cartService = $cartService;
        $this->seoUrl = $ceoUrl;
        $this->repository = $entityRepository;
    }

    /**
     * @param SalesChannelContext $context
     * @return array|null
     */
    public function getCart(SalesChannelContext $context): ?array
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        if ($cart->getLineItems()->count() == 0) {
            return null;
        }
        $items=[];
        foreach ($cart->getLineItems()->getElements() as $item) {
            //ID is a Hexadecimal value, but needs to be a decimal. So we use hexdec php functionality to make it an number
            //So the Belco backend can get this value.
            $items[] = array('id' => hexdec($item->getId()),
                'name' => $item->getLabel(),
                'price' => (float)$item->getPrice()->getUnitPrice(),
                'url' => $this->getProductUrl($context, $item),
                'quantity' => (int)$item->getQuantity());
        }
        return array(
            'total' => (float)$cart->getPrice()->getTotalPrice(),
            'subtotal' => (float)$cart->getPrice()->getNetPrice(),
            'currency' => $context->getCurrency()->getIsoCode(),
            'items' => $items,
        );
    }

    /**
     * @return array Returns a Array with the User information
     */
    public function getCustomer(SalesChannelContext $context): array
    {
        $customer = array();

        if ($context->getCustomer() != null) {

            $user = $context->getCustomer();
            $customer = array(
                'id' => $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
                'country' => $context->getCurrency()->getIsoCode(),
                'signedUp' => ($user->getFirstLogin())
            );

            if ($context->getCustomer()->getDefaultBillingAddress()->getPhoneNumber() != null) {
                $customer['phoneNumber'] = $context->getCustomer()->getDefaultBillingAddress()->getPhoneNumber();
            }
        }

        return $customer;
    }

    /**
     * @param $context
     * @param $customerId
     * @return array|null
     */
    private function getOrderData($context, $customerId): ?array
    {
        $criteria = new Criteria();

        $criteria->addAggregation(
            new SumAggregation('totalSpent', 'amountTotal')
        );
        $criteria->addAggregation(
            new MaxAggregation('lastOrder', 'orderDateTime')
        );
        $criteria->addAggregation(
            new CountAggregation('orderCount', 'id')
        );

        $criteria->addAssociation('OrderCustomer');
        $criteria->addFilter(
            new EqualsFilter('orderCustomer.customerId', $customerId)
        );
        $result = $this->repository->search($criteria, $context);

        if ($result->getAggregations()) {
            $aggregations = $result->getAggregations();
            return array(
                'totalSpent' => (float)$aggregations->get('totalSpent')->getVars()['sum'],
                'lastOrder' => strtotime($aggregations->get('lastOrder')->getVars()['max']),
                'orderCount' => (int)$aggregations->get('orderCount')->getVars()['count']
            );
        }
        return null;
    }

    /**
     * @param SalesChannelContext $salesContext
     * @param Context $context
     * @return string
     */
    public function getWidgetConfig(SalesChannelContext $salesContext, Context $context): string
    {
        $customer = $this->getCustomer($salesContext);

        $belcoConfig = array(
            'shopId' => $this->getConfig()['shopId'],
            'cart' => $this->getCart($salesContext)
        );
//        dd($belcoConfig);
        if ($customer) {
            $belcoConfig = array_merge($belcoConfig, $customer);
            $order = $this->getOrderData($context, $customer['id']);
            if ($order) {
                $belcoConfig = array_merge($belcoConfig, $order);
            }
            if ($this->getConfig()['apiSecret']) {
                $belcoConfig['hash'] = hash_hmac('sha256', $customer['id'], $this->getConfig()['apiSecret']);
            }
        }

        return json_encode($belcoConfig);
    }

    private function getConfig(): array
    {
        return $this->systemConfigService->get('BelcoShopware.config');
    }


    /**
     * @param FooterPageletLoadedEvent $event
     */
    public function onPostDispatch(FooterPageletLoadedEvent $event): void
    {
        if (!$this->getConfig()['shopId']) {
            return;
        }
        $salesContext = $event->getSalesChannelContext();
        $context = $event->getContext();
        $pagelet = $event->getPagelet();

        $shopId = $this->getConfig()['shopId'];

        if (!$this->getConfig()['shopId']) {
            return;
        }

        $belcoConfig = $this->getWidgetConfig($salesContext, $context);

        $optionsConfig = array(
            'belcoConfig' => $belcoConfig,
            'shopId' => $shopId
        );
        $pagelet->assign($optionsConfig);
    }

    /**
     * @param SalesChannelContext $context
     * @param LineItem $item
     * @return string
     */
    private function getProductUrl(SalesChannelContext $context, LineItem $item): string
    {
        return $this->seoUrl->replace(
            $this->seoUrl->generate('frontend.detail.page', ['productId' => $item->getId()]),
            $this->getConfig()['domainName'],
            $context);
    }
}
