<?php


namespace Nextorder\Menue\Controller\Index;
use Magento\Framework\App\Action\Context;

class Cart extends \Magento\Framework\App\Action\Action{

    protected $_cart;
    protected $_productRepository;
//    protected $_resultJsonFactory;
    protected $_idsAndOptionIds;
    protected $_checkoutSession;
    protected $_scopeConfig;

    public function __construct(
                                Context $context,
                                \Magento\Checkout\Model\Cart $cart,
                                \Magento\Checkout\Model\Session $checkoutSession,
//                                \Magento\Checkout\Model\Session\Interceptor $interceptor,
                                \Magento\Catalog\Model\ProductRepository $productRepository,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ){
        $this->_cart = $cart;
        $this->_checkoutSession = $checkoutSession;
        $this->_productRepository = $productRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->_idsAndOptionIds = $this->getIdAndOptionId('inc','toCartConfig.txt');
        parent::__construct($context);
    }
    public function execute(){

        if ($this->getRequest()->getParam("menu_orders")){
            $menu_orders_skus = explode(",", $this->getRequest()->getParam("menu_orders"));
            if($this->addProductsInCart($menu_orders_skus)){echo "worked";}
        }
//        $resultRedirect = $this->resultRedirectFactory->create();
//        return $resultRedirect->setPath('checkout/cart/index');
    }
/*
 *  generate Orders in Cart
 * @var orders array
 */
    protected function addProductsInCart($skus){

        $bundle_option = array();
        $bundle_option_qty = array();
        $counts = array_count_values($skus);
        foreach ($skus as $sku){
            if(empty($sku)){continue;}
            $bundle_option[$this->_idsAndOptionIds[$sku]['option_id']] = $this->_idsAndOptionIds[$sku]['selection_id'];

            $bundle_option_qty[$this->_idsAndOptionIds[$sku]['option_id']] = $counts[$sku];
        }

        $params = [
            'uenc' => null,
            'product' => $this->_idsAndOptionIds[$this->_scopeConfig->getValue('menu/menu_group_1/menu_group_1_field_1')],
            'selected_configurable_option' => null,
            'related_product' => null,
            'form_key' => null,
            'bundle_option' => $bundle_option,
            'bundle_option_qty' => $bundle_option_qty,
            'qty' => 1
        ];
        if (isset($params['qty'])) {
            $filter = new \Zend_Filter_LocalizedToNormalized(
                ['locale' => $this->_objectManager->get('Magento\Framework\Locale\ResolverInterface')->getLocale()]
            );
        }
            $params['qty'] = $filter->filter($params['qty']);
        $storeId = $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore()->getId();
        $product = $this->_productRepository->getById($this->_idsAndOptionIds[$this->_scopeConfig->getValue('menu/menu_group_1/menu_group_1_field_1')], false, $storeId);
        $this->_cart->addProduct($product,$params);
        $this->_cart->save();
        $this->_eventManager->dispatch(
            'checkout_cart_add_product_complete',
            ['product' => $product, 'request' => $this->getRequest(), 'response' => $this->getResponse()]
        );
        if (!$this->_checkoutSession->getNoCartRedirect(true)) {
            if (!$this->_cart->getQuote()->getHasError()) {
                $message = __(
                    'You added %1 to your shopping cart.',
                    $product->getName()
                );
                $this->messageManager->addSuccessMessage($message);
            }
        }
        return true;
    }
    /*
     * get related Id and Option id according to sku
     */
    public function getIdAndOptionId($dir, $file){
        $serializedArray = file_get_contents($this->df_module_dir("Nextorder_Menue")."/".$dir."/".$file);
        return unserialize($serializedArray);
    }
    /*
    * get module dir to save serialized array of option ids
    */
    public  function df_module_dir($moduleName, $type = '') {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\Module\Dir\Reader $reader */
        $reader = $om->get('Magento\Framework\Module\Dir\Reader');
        return $reader->getModuleDir($type, $moduleName);
    }
}