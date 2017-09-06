<?php


namespace Nextorder\Menue\Controller\Index;
use Magento\Framework\App\Action\Context;

class Cart extends \Magento\Framework\App\Action\Action{

    protected $_cart;
    protected $_productFactory;
//    protected $_resultJsonFactory;
    protected $_idsAndOptionIds;
    protected $_checkoutSession;
    protected $_scopeConfig;
    protected $_logger;
    public $_helper;

    public function __construct(
                                Context $context,
                                \Magento\Checkout\Model\Cart $cart,
                                \Magento\Checkout\Model\Session $checkoutSession,
                                \Magento\Catalog\Model\ProductFactory $productFactory, //product Factory injection
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Psr\Log\LoggerInterface $logger,
                                \Nextorder\Menue\Helper\Data $helper //helper injection
    ){
        $this->_helper = $helper;
        $this->_cart = $cart;
        $this->_checkoutSession = $checkoutSession;
        $this->_productFactory = $productFactory->create();
        $this->_scopeConfig = $scopeConfig;
//        $this->_idsAndOptionIds = $this->_helper('inc','toCartConfig.txt');
        $this->_logger = $logger;
        parent::__construct($context);
    }
    public function execute(){
        if ($this->getRequest()->getParam("menu_orders")){
            $menu_orders_skus = explode(",", $this->getRequest()->getParam("menu_orders"));
            if($this->addProductsInCart($menu_orders_skus)){echo "worked";}
        }
    }
/*
 *  generate Orders in Cart
 * @var orders array
 */
    protected function addProductsInCart($skus){

        $bundle_option = array();
        $bundle_option_qty = array();
        $counts = array_count_values($skus);

        $bundledatas = $this->_helper->getSerializedData('inc','bundleDataSource.txt');
        $optionIds =array_keys($bundledatas);

        $bundleProduct = $this->_productFactory->loadByAttribute('sku', $this->_helper->getBundleProductSku());
        $bundleProductId = $bundleProduct->getId();

//        $this->_logger->addDebug(print_r($bundleProductId, true));
//        $this->_logger->addDebug(print_r($skus, true));

        $index = 0;
        foreach ($bundledatas as $bundledata){
            $currentSKU = $skus[$index];
            if($currentSKU === 'empty'){$index++; continue;}
            $currentOptionId = $optionIds[$index];
            $currentSelectionId = $bundledata[$currentSKU];
            $bundle_option[$currentOptionId] = $currentSelectionId;
            $bundle_option_qty[$currentOptionId] = 1;
            $index++;
        }


//        $this->_logger->addDebug(print_r($bundle_option, true));
//        $this->_logger->addDebug(print_r($bundle_option_qty, true));



//        $bundle = $this->_productRepository->get($this->_scopeConfig->getValue('menu/menu_group_1/menu_group_1_field_1'));
//        $selectionCollection = $bundle->getTypeInstance(true)
//            ->getSelectionsCollection(
//                $bundle->getTypeInstance(true)->getOptionsIds($bundle),
//                $bundle
//            );
//        $optionIds = array();
//        foreach ($selectionCollection as $selection) {
//            if (!in_array($selection->getData('option_id'),$optionIds)) {
//                $optionIds[] = $selection->getData('option_id');
//            }
//        }
//        $o = 0;
//        foreach ($skus as $sku){
//            if(empty($sku)){continue;}
//            $bundle_option[$optionIds[$o]] = $this->_idsAndOptionIds[$sku]['selection_id'];
//            $bundle_option_qty[$optionIds[$o]] = $counts[$sku];
//            $o++;
//        }
//
//
//
//
//
//
        $params = [
            'uenc' => null,
            'product' => $bundleProductId,
            'selected_configurable_option' => null,
            'related_product' => null,
            'form_key' => null,
            'bundle_option' => $bundle_option,
            'bundle_option_qty' => $bundle_option_qty,
            'qty' => 1
        ];

////        $this->_logger->addDebug(json_encode($params));
        if (isset($params['qty'])) {
            $filter = new \Zend_Filter_LocalizedToNormalized(
                ['locale' => $this->_objectManager->get('Magento\Framework\Locale\ResolverInterface')->getLocale()]
            );
        }

        $params['qty'] = $filter->filter($params['qty']);
//        $storeId = $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore()->getId();
//        $product = $this->_productRepository->getById($this->_idsAndOptionIds[$this->_scopeConfig->getValue('menu/menu_group_1/menu_group_1_field_1')], false, $storeId);
//
//        $this->_logger->addDebug(print_r($params, true));
//
        $this->_cart->addProduct($bundleProduct,$params);
        $this->_cart->save();
        $this->_eventManager->dispatch(
            'checkout_cart_add_product_complete',
            ['product' => $bundleProduct, 'request' => $this->getRequest(), 'response' => $this->getResponse()]
        );

        if (!$this->_checkoutSession->getNoCartRedirect(true)) {
            if (!$this->_cart->getQuote()->getHasError()) {
                $message = __(
                    'You added %1 to your shopping cart.',
                    $bundleProduct->getName()
                );
                $this->messageManager->addSuccessMessage($message);
            }
        }
        return true;
    }
}