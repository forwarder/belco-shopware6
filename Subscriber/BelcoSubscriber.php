<?php

namespace BelcoShopware\Subscriber;

use Doctrine\ORM\NonUniqueResultException;
use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use Enlight_Exception;
use Shopware\Bundle\CookieBundle\CookieCollection;
use Shopware\Bundle\CookieBundle\Structs\CookieGroupStruct;
use Shopware\Bundle\CookieBundle\Structs\CookieStruct;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;

/**
 * Class BelcoSubscriber
 * @package BelcoShopware\Subscriber
 */
class BelcoSubscriber implements SubscriberInterface
{
    private $pluginDirectory;
    private $config;

    public function activate(ActivateContext $context) {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    public function uninstall(UninstallContext $context)
    {
        if ($context->keepUserData()) {
            return; //NOSONAR
        }
    }

    public static $repository = null;

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onPostDispatch',
            'CookieCollector_Collect_Cookies' => 'addBelcoCookie'
        ];
    }

    /**
     * BelcoSubscriber constructor.
     * @param $pluginName
     * @param $pluginDirectory
     * @param ConfigReader $configReader
     */
    public function __construct($pluginDirectory, ConfigReader $configReader)
    {
        $this->pluginDirectory = $pluginDirectory;

        $this->config = $configReader->getByPluginName('BelcoShopware');
    }

    /**
     * Get the Cart of Shopware
     * @return array|null The {@link \sBasket} data. represented as the cart.
     * @throws Enlight_Exception
     */
    public function getCart(): ?array
    {
        $cart = Shopware()->Modules()->Basket()->sGetBasketData();
        if (empty($cart['content'])) {
            return null;
        }

        return array(
            'total' => (float)$cart['AmountNumeric'],
            'subtotal' => (float)$cart['AmountNetNumeric'],
            'currency' => $this->getCurrency(),
            'items' => array_map(function ($item) {
                return array(
                    'id' => $item['articleID'],
                    'name' => $item['articlename'],
                    'price' => (float)$item['priceNumeric'],
                    'url' => $item['linkDetails'],
                    'quantity' => (int)$item['quantity']
                );
            }, $cart['content'])
        );

    }

    /**
     * @return array Returns a Array with the User information
     */
    public function getCustomer():array
    {
        $data = Shopware()->Modules()->Admin()->sGetUserData();
        $customer = array();

        if (!empty($data['additional']['user'])) {
            $user = $data['additional']['user'];

            $customer = array(
                'id' => $user['id'],
                'firstName' => $user['firstname'],
                'lastName' => $user['lastname'],
                'email' => $user['email'],
                'country' => $data['additional']['country']['countryiso'],
                'signedUp' => strtotime($user['firstlogin'])
            );

            if ($data['billingaddress']['phone']) {
                $customer['phoneNumber'] = $data['billing']['phone'];
            }
        }

        return $customer;
    }

    /**
     * @param $customerId
     * @return array
     * @throws NonUniqueResultException
     */
    private function getOrderData($customerId): ?array
    {
        $builder = Shopware()->Models()->createQueryBuilder();

        $builder->select(array(
            'SUM(orders.invoiceAmount) as totalSpent',
            'MAX(orders.orderTime) as lastOrder',
            'COUNT(orders.id) as orderCount',
        ));

        $builder
            ->from('Shopware\Models\Order\Order', 'orders')
            ->groupBy('orders.customerId')
            ->where($builder->expr()->eq('orders.customerId', $customerId))
            ->andWhere($builder->expr()->notIn('orders.status', array('-1', '4')))
            ->addOrderBy('orders.orderTime', 'ASC');

        $result = $builder->getQuery()->getOneOrNullResult();

        if ($result) {
            return array(
                'totalSpent' => (float)$result['totalSpent'],
                'lastOrder' => strtotime($result['lastOrder']),
                'orderCount' => (int)$result['orderCount']
            );
        }
        return null;
    }

    /**
     * @return false|string
     * @throws NonUniqueResultException
     * @throws Enlight_Exception
     */
    public function getWidgetConfig()
    {
        $customer = $this->getCustomer();

        $belcoConfig = array(
            'shopId' => $this->config['shopId'],
            'cart' => $this->getCart()
        );

        if ($customer) {
            $belcoConfig = array_merge($belcoConfig, $customer);
            $order = $this->getOrderData($customer['id']);
            if ($order) {
                $belcoConfig = array_merge($belcoConfig, $order);
            }
            if ($this->config['apiSecret']) {
                $belcoConfig['hash'] = hash_hmac('sha256', $customer['id'], $this->config['apiSecret']);
            }
        }

        return json_encode($belcoConfig);
    }


    /**
     * @param Enlight_Controller_ActionEventArgs $args
     * @throws NonUniqueResultException
     * @throws Enlight_Exception
     */
    public function onPostDispatch(Enlight_Controller_ActionEventArgs $args)
    {
        $controller = $args->get('subject');
        $view = $controller->View();

        $shopId = $this->config['shopId'];

        if (!$this->config['shopId']) {
            return;
        }

        $belcoConfig = $this->getWidgetConfig();

        $view->assign('belcoConfig', $belcoConfig);
        $view->assign('shopId', $shopId);

        $view->addTemplateDir($this->pluginDirectory . '/Resources/views');
    }

    /**
     * Shopware needs to register the belco cookies.
     * Here we determine the belco cookies to be saved on the technical level of the cookies.
     * @return CookieCollection
     */
    public function addBelcoCookie(): CookieCollection
    {
        $collection = new CookieCollection();
        $collection->add(new CookieStruct(
            'belco',
            '/(^belco-\w+-\w+)|(^belco-\w+)/',
            'Chat session',
            CookieGroupStruct::TECHNICAL
        ));
        return $collection;
    }

    /**
     * Returns the currency used for the Shop.
     * @return string Currency in the shortName form. Like EUR, USD etc.
     */
    private function getCurrency(): string
    {
        return Shopware()->Shop()->getCurrency()->getCurrency();
    }
}
