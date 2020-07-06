<?php 

namespace BelcoConnectorPlugin\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\Context\ActivateContext;
use Shopware\Components\Context\UninstallContext;
use Shopware\Components\Plugin\ConfigReader;
use BelcoConnectorPlugin\Components\BelcoConnector;

class BelcoSubscriber implements SubscriberInterface{
    private $belcoConnector;
    private $pluginDirectory;
    private $config;
    
    public function activate(ActivateContext $context) { //Belco::activate() must be an instance of Belco\\ActivateContext, instance of Shopware\\Components\\Plugin\\Context\\ActivateContext
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    public function uninstall(UninstallContext $context) {
        if ($context->keepUserData()) {
            return;
        }
    }

    public static $repository = null;

    public static function getSubscribedEvents() {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onPostDispatch'
        ];
    }

    public function __construct($pluginName, $pluginDirectory, BelcoConnector $belcoConnector, ConfigReader $configReader)
    {
        $this->pluginDirectory = $pluginDirectory;
        $this->belcoConnector = $belcoConnector;

        $this->config = $configReader->getByPluginName('BelcoConnectorPlugin');
    }

    public function getCart() {
        $cart = Shopware()->System()->sMODULES['sBasket']->sGetBasketData();

        if (empty($cart['content'])) {
            return null;
        }

        return array(
            'total' => (float) $cart['AmountNumeric'],
            'subtotal' => (float) $cart['AmountNetNumeric'],
            'currency' => $this->getCurrency(),
            'items' => array_map(function($item) {
                return array(
                    'id' => $item['articleID'],
                    'name' => $item['articlename'],
                    'price' => (float) $item['priceNumeric'],
                    'url' => $item['linkDetails'],
                    'quantity' => (int) $item['quantity']
                );
            }, $cart['content'])
        );
    }

    public function getCustomer() {
        $data = Shopware()->System()->sMODULES['sAdmin']->sGetUserData();

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

    private function getOrderData($customerId) {
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
                'totalSpent' => (float) $result['totalSpent'],
                'lastOrder' => strtotime($result['lastOrder']),
                'orderCount' => (int) $result['orderCount']
            );
        }
    }

    public function getWidgetConfig() {
        $customer = $this->getCustomer(); 

        $belcoConfig = array(
            'shopId' => $this->config['shopId'],
            'cart' => $this->getCart()
        );

        if ($customer) {
            $order = $this->getOrderData($customer['id']);

            $belcoConfig = array_merge($config, $customer, $order);

            if ($this->config['apiSecret']) {
                $belcoConfig['hash'] = hash_hmac('sha256', $customer['id'], $this->config['apiSecret']);
            }
        }

        return json_encode($belcoConfig);
    }

    public function onPostDispatch(\Enlight_Controller_ActionEventArgs $args) { 
        $controller = $args->get('subject');
        $view = $controller->View();

        $shopId = $this->config['shopId'];

        if (!$this->config['shopId']) {
            return;
        }

        $belcoConfig = $this->getWidgetConfig();

        $view->assign('belcoConfig', $belcoConfig);

        $view->addTemplateDir($this->pluginDirectory . '/Resources/views');
    }

    private function getCurrency() {
        return $this->get('currency')->getShortName();
    }
}
