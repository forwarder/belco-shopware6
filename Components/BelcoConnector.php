<?php 

namespace BelcoConnectorPlugin\Components;

class BelcoConnector {
    public function install() {

        $this->createConfig();

        return true;
    }

    private function getCurrency() {
        return $this->get('currency')->getShortName();
    }

    public function onFrontendPostDispatch(Enlight_Event_EventArgs $args) {
        /** @var \Enlight_Controller_Action $controller */ 
        $controller = $args->get('subject');
        $view = $controller->View();

        $view->addTemplateDir(
            __DIR__ . '/Views'
        );

        $view->assign('belcoConfig', $this->getWidgetConfig());
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

    public function getWidgetConfig() {
        $shopId = $this->Config()->get('shopId');

        if (!$shopId) {
            return;
        }

        $config = array(
            'shopId' => $shopId,
            'cart' => $this->getCart()
        );

        $customer = $this->getCustomer();

        if ($customer) {
        $order = $this->getOrderData($customer['id']);

        $config = array_merge($config, $customer, $order);
        }

        return json_encode($config);
    }

    private function createConfig() {
        $this->Form()->setElement('text', 'shopId', array(
            'label' => 'Shop Id'
        ));

        $this->Form()->setElement('text', 'apiKey', array(
            'label' => 'Api Key'
        ));
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
}