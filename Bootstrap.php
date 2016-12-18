 <?php
 class Shopware_Plugins_Frontend_Belco_Bootstrap extends Shopware_Components_Plugin_Bootstrap {
  
  public static $repository = null;

  public function getVersion() {
    return '1.0.0';
  }

  public function getLabel() {
    return 'Belco';
  }

  public function getInfo() {
    return array(
      'version' => $this->getVersion(),
      'author' => 'Forwarder B.V.',
      'source' => $this->getSource(),
      'supplier' => 'Forwarder B.V.',
      'support' => 'help@belco.io',
      'link' => 'https://www.belco.io',
      'copyright' => 'Copyright (c) 2016, Forwarder B.V.',
      'label' => $this->getLabel(),
      'description' => '<h2>Sales & Support tool for e-commerce</h2>'
    );
  }

  public function install() {
    $this->subscribeEvent(
      'Enlight_Controller_Action_PostDispatchSecure_Frontend',
      'onFrontendPostDispatch'
    );

    // $this->subscribeEvent(
    //   'Shopware_Modules_Admin_SaveRegister_Successful',
    //   'onSaveRegisterSuccessful'
    // );

    // $this->subscribeEvent(
    //   'Enlight_Controller_Action_PostDispatch_Frontend_Account',
    //   'onFrontendAccountPostDispatch'
    // );

    // $this->subscribeEvent(
    //   'Enlight_Controller_Action_PostDispatch_Frontend_Checkout',
    //   'onCheckoutConfirm'
    // );

    $this->createConfig();

    return true;
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

  // public function onSaveRegisterSuccessful(Enlight_Event_EventArgs $args) {
  //   /** @var \Enlight_Controller_Action $controller */
  //   error_log($args);
  // }

  // public function onFrontendAccountPostDispatch(Enlight_Event_EventArgs $args) {
  //   /** @var \Enlight_Controller_Action $controller */
  //   error_log($args);
  // }

  // public function onCheckoutConfirm(Enlight_Event_EventArgs $args) {
  //   /** @var \Enlight_Controller_Action $controller */
  //   error_log($args);
  // }

  public function getCart() {
    $cart = Shopware()->System()->sMODULES['sBasket']->sGetBasketData();

    if (empty($cart['content'])) {
      return null;
    }

    return array(
      'total' => $cart['AmountNumeric'],
      'subtotal' => $cart['AmountNetNumeric'],
      'items' => array_map(function($item) {
        return array(
          'id' => $item['articleID'],
          'name' => $item['articlename'],
          'price' => $item['priceNumeric'],
          'url' => $item['linkDetails'],
          'quantity' => $item['quantity']
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

      // @TODO add more data
      // - Last order
      // - Customer value
      // - Total orders
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
      $config = array_merge($config, $customer);
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
}