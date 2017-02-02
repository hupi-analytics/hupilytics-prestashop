<?php
/**
 *   2009-2016 ohmyweb!
 *
 *   @author    ohmyweb <contact@ohmyweb.fr>
 *   @copyright 2009-2016 ohmyweb!
 *   @license   Proprietary - no redistribution without authorization
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Hupilytics extends Module
{
    protected $config_form = false;
    
    protected $js_state = 0;
    protected $eligible = 0;
    protected $filterable = 1;
    protected static $products = array();
    protected $_debug = 0;
    
    protected static $impressionProducts = array();
    protected static $recommendedProducts = array();
    
    public function __construct()
    {
        $this->name = 'hupilytics';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.5';
        $this->author = 'Hupi';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Hupi Analytics');
        $this->description = $this->l('This allow to send analytics data within hupi\'s servers');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall my module?');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('HUPI_ACCOUNT_ID', null);
        Configuration::updateValue('HUPI_SITE_ID', null);
        Configuration::updateValue('HUPI_USERID_ENABLED', null);
        
        include(dirname(__FILE__).'/sql/install.php');
        
        $this->updateConfigFile('install');
        
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('actionCartSave') &&
            $this->registerHook('displayAdminOrderContentOrder') &&
            $this->registerHook('displayHome') &&
            $this->registerHook('displayFooter') &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('displayHomeTab') &&
            $this->registerHook('displayHomeTabContent') &&
            $this->registerHook('productTab') &&
            $this->registerHook('productTabContent') &&
            $this->registerHook('displayShoppingCart') &&
            $this->registerHook('displayHupiRecommendations')
        ;
    }

    public function uninstall()
    {
        Configuration::deleteByName('HUPI_ACCOUNT_ID');
        Configuration::deleteByName('HUPI_SITE_ID');
        Configuration::deleteByName('HUPI_USERID_ENABLED');
        
        include(dirname(__FILE__).'/sql/uninstall.php');
        
        $this->updateConfigFile('uninstall');
        
        return parent::uninstall();
    }
    
    
    private function codeModificationFile()
    {
        $modif = '<li{if isset($id) && $id == "hupirecommend"} data-product="{$product.id_product}"{/if} ';
        return array('../themes/'._THEME_NAME_.'/product-list.tpl' =>
            array(
                'before'    => '<li ',
                'after'     => $modif,
                'search'    => 'li_combi_'
            ));
    }
    
    private function updateConfigFile($action = 'install')
    {
        foreach ($this->codeModificationFile() as $file => $modification)
        {
            if (!file_exists($file)) {
                return false;
            }
             
            list ($before, $after) = ($action == 'uninstall')? array($modification['after'], $modification['before']) : array($modification['before'], $modification['after']);
            $chmod = fileperms($file);
            $content = Tools::file_get_contents($file);
    
            if (strstr($content, $modification['search']) && $action == 'install' || !strstr($content, $modification['search']) && $action != 'install') {
                return true;
            }
    
            $content = str_replace($before, $after, $content);
            chmod($file, 0777);
            if (!$fp = fopen ($file, 'w')) {
                chmod($file, $chmod);
                fclose ($fp);
                return false;
            }
             
            fwrite ($fp, $content);
            fclose ($fp);
            chmod($file, $chmod);
        }
        return true;
    }
    private function testModificationsFiles()
    {
        $html = '';
        foreach ($this->codeModificationFile() as $file => $modification)
        {
            if (!file_exists($file)) {
                $html .= $this->displayError($this->l('Impossible to find the file').' : '.$file);
            } else {
                if (!$this->fileModified($file, $modification['after'])) {
                    $html .= $this->displayError(
                        $this->l('To be able to track the clicks of recommendations thank you to replace').' : <br/><code>'.Tools::htmlentitiesUTF8($modification['before']).'</code>'.$this->l(' by ').'<code>'.Tools::htmlentitiesUTF8($modification['after']).'</code><br/>'.$this->l('in the file').' <strong>'.$file.'</strong>');
                }
            }
        }
        return $html;
    }
    
    private function fileModified($file, $search = false)
    {
        $search = (!$search)? MD5($this->name) : $search;
        if ($page_res = Tools::file_get_contents($file)) {
            return mb_strpos($page_res, $search);
        }
        return false;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        
        if(Tools::getValue('ajax')) {
            $this->dispatchAjax();
        }
        else {
            $output = '';
            /**
             * If values have been submitted in the form, process.
             */
            if (((bool)Tools::isSubmit('submitHupilyticsModule')) == true) {
                $this->postProcess();
            }
    
            if (version_compare(_PS_VERSION_, '1.5', '>='))
                $output .= $this->renderForm();
            else
            {
                $this->context->smarty->assign(array(
                    'account_id' => Configuration::get('HUPI_ACCOUNT_ID'),
                    'site_id' => Configuration::get('HUPI_ACCOUNT_ID'),
                    'userid_enabled' => Configuration::get('HUPI_USERID_ENABLED'),
                ));
                $output .= $this->display(__FILE__, 'views/templates/admin/form-ps14.tpl');
            }

            if($messageErrorFile = $this->testModificationsFiles()) {
                $this->context->smarty->assign('errorFile', $messageErrorFile);
            }
            
            $token = Configuration::get('HUPIRECO_TOKEN');
            $active_recommendation = Configuration::get('HUPIRECO_ACTIVE');
            
            $product_page = array(
                'active' => Configuration::get('HUPIRECO_PROD_ACTIVE'),
                'end_point' => Configuration::get('HUPIRECO_PROD_ENDPOINT'),
                'nb_products' => Configuration::get('HUPIRECO_PROD_NB'),
            );
            $shopping_cart = array(
                'active' => Configuration::get('HUPIRECO_CART_ACTIVE'),
                'end_point' => Configuration::get('HUPIRECO_CART_ENDPOINT'),
                'nb_products' => Configuration::get('HUPIRECO_CART_NB'),
            );
            $homepage = array(
                'active' => Configuration::get('HUPIRECO_HP_ACTIVE'),
                'end_point' => Configuration::get('HUPIRECO_HP_ENDPOINT'),
                'nb_products' => Configuration::get('HUPIRECO_HP_NB'),
            );
            if (Tools::isSubmit('updateConfig')) {
                $token = Tools::getValue('hupireco_token');
                $active_recommendation = Tools::getValue('active_recommendation');
                
                Configuration::updateValue('HUPIRECO_TOKEN', $token);
                Configuration::updateValue('HUPIRECO_ACTIVE', $active_recommendation);
            
                if($product_page = Tools::getValue('product_page')) {
                    Configuration::updateValue('HUPIRECO_PROD_ACTIVE', $product_page['active']);
                    Configuration::updateValue('HUPIRECO_PROD_ENDPOINT', $product_page['end_point']);
                    Configuration::updateValue('HUPIRECO_PROD_NB', $product_page['nb_products']);
                }
                if($shopping_cart = Tools::getValue('shopping_cart')) {
                    Configuration::updateValue('HUPIRECO_CART_ACTIVE', $shopping_cart['active']);
                    Configuration::updateValue('HUPIRECO_CART_ENDPOINT', $shopping_cart['end_point']);
                    Configuration::updateValue('HUPIRECO_CART_NB', $shopping_cart['nb_products']);
                }
                if($homepage = Tools::getValue('homepage')) {
                    Configuration::updateValue('HUPIRECO_HP_ACTIVE', $homepage['active']);
                    Configuration::updateValue('HUPIRECO_HP_ENDPOINT', $homepage['end_point']);
                    Configuration::updateValue('HUPIRECO_HP_NB', $homepage['nb_products']);
                }
            }
            
            $this->context->controller->addCSS($this->_path.'views/css/hupirecommend.css');
            $this->context->smarty->assign(array(
                'module_dir' => $this->_path,
                'active_recommendation' => $active_recommendation,
                'hupireco_token' => $token,
                'product_page' => $product_page,
                'shopping_cart' => $shopping_cart,
                'homepage' => $homepage,
            ));
            return $output.$this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
            
        }
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitHupilyticsModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        // Load current value
        $helper->fields_value['HUPI_ACCOUNT_ID'] = Configuration::get('HUPI_ACCOUNT_ID');
        $helper->fields_value['HUPI_SITE_ID'] = Configuration::get('HUPI_SITE_ID');
        $helper->fields_value['HUPI_USERID_ENABLED'] = Configuration::get('HUPI_USERID_ENABLED');
        
        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
    			'legend' => array(
    				'title' => $this->l('Settings'),
    			),
    			'input' => array(
    				array(
    					'type' => 'text',
    					'label' => $this->l('Hupi Analytics Account ID'),
    					'name' => 'HUPI_ACCOUNT_ID',
    					'size' => 20,
    					'required' => true,
    					'hint' => $this->l('This information is provided by Hupi')
    				),
    				array(
    					'type' => 'text',
    					'label' => $this->l('Hupi Analytics Site ID'),
    					'name' => 'HUPI_SITE_ID',
    					'size' => 20,
    					'required' => true,
    					'hint' => $this->l('This information is provided by Hupi')
    				),
    				array(
    					'type' => 'radio',
    					'label' => $this->l('Enable User ID tracking'),
    					'name' => 'HUPI_USERID_ENABLED',
    					'hint' => $this->l('The User ID is set at the property level. To find a property, click Admin, then select an account and a property. From the Property column, click Tracking Info then User ID'),
    					'values'    => array(
    						array(
    							'id' => 'hupi_userid_enabled',
    							'value' => 1,
    							'label' => $this->l('Enabled')
    						),
    						array(
    							'id' => 'hupi_userid_disabled',
    							'value' => 0,
    							'label' => $this->l('Disabled')
    						),
    					),
    				),
    			),
    			'submit' => array(
    				'title' => $this->l('Save'),
    			)
    		)
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'HUPI_ACCOUNT_ID' => Configuration::get('HUPI_ACCOUNT_ID'),
            'HUPI_SITE_ID' => Configuration::get('HUPI_SITE_ID'),
            'HUPI_USERID_ENABLED' => Configuration::get('HUPI_USERID_ENABLED'),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }
    
    protected function getProducts($endpoint, $nbProducts = 4, $product = null) {
        $id_lang = $this->context->language->id;
    
        if($this->context->cookie->__get('pk_id') && Configuration::get('HUPIRECO_TOKEN')) {
            $arrayPkId = explode('.', $this->context->cookie->__get('pk_id'));
            $visitor_id = array_shift($arrayPkId);
            $filters = array("visitor_id" => $visitor_id);
            if($product) {
                $filters['id_demande'] = $product;
            }
            if($products = $this->context->cart->getProducts(true)) {
                $productList = array();
                foreach ($products as $product) {
                    $productList[] = (int)$product['id_product'];
                }
                if($productList) {
                    $filters['basket'] = json_encode($productList);
                }
            }
    
            $data = array("client" => Configuration::get('HUPI_ACCOUNT_ID'), "render_type" => "cursor", "filters" => json_encode($filters));
            $data_string = json_encode($data);
    
            $ch = curl_init("https://api.dataretriever.hupi.io/private/".Configuration::get('HUPI_ACCOUNT_ID')."/".$endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Accept-Version: v1',
                'X-API-Token: '.Configuration::get('HUPIRECO_TOKEN'),
                'Content-Length: ' . strlen($data_string)
            ));
             
            $result = curl_exec($ch);
            curl_close($ch);
    
            $jsonArray = json_decode($result, true);
            if($jsonArray['data'] && count($jsonArray['data'])) {
                if(key(current($jsonArray['data'])) == 'idRs') {
                    $idProducts = current(array_values(array_map('current',$jsonArray['data'])));
    
                }
                else {
                    $idProducts = array_values(array_map('current',$jsonArray['data']));
                }
    
                $sql = 'SELECT p.*, product_shop.*, stock.`out_of_stock` out_of_stock, pl.`description`, pl.`description_short`,
                            pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`,
                            p.`ean13`, p.`upc`, MAX(image_shop.`id_image`) id_image, il.`legend`
                        FROM `'._DB_PREFIX_.'product` p
                        LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (
                            p.`id_product` = pl.`id_product`
                            AND pl.`id_lang` = '.(int)$id_lang.Shop::addSqlRestrictionOnLang('pl').'
                        )
                        '.Shop::addSqlAssociation('product', 'p').'
                        LEFT JOIN `'._DB_PREFIX_.'image` i ON (i.`id_product` = p.`id_product`)'.
                            Shop::addSqlAssociation('image', 'i', false, 'image_shop.cover=1').'
                        LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = '.(int)$id_lang.')
                        '.Product::sqlStock('p', 0).'
                        WHERE 1
                            AND p.`id_product` IN ('.implode(',', $idProducts).')
                            AND product_shop.`visibility` IN ("both", "catalog")
                            AND product_shop.`active` = 1
                        GROUP BY product_shop.id_product
                        ORDER BY FIELD(p.`id_product`, '.implode(',', $idProducts).')
                        LIMIT 0, '. (int)$nbProducts;
    
                $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
                return Product::getProductsProperties($id_lang, $result);
            }
        }
    
        return array();
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        if (Configuration::get('HUPI_ACCOUNT_ID'))
        {
            $this->context->controller->addJS($this->_path.'views/js/hupilytics.js');
            $this->context->controller->addJS($this->_path.'views/js/hupilytics-action-lib.js');

            return $this->_getHupilyticsTag();
        }
        
    }
    
    	/**
	 * wrap products to provide a standard products information for google analytics script
	 */
	public function wrapProducts($products, $extras = array(), $full = false)
	{
	    $result_products = array();
	    if (!is_array($products))
	        return;
	    
        $currency = new Currency($this->context->currency->id);
        $usetax = (Product::getTaxCalculationMethod((int)$this->context->customer->id) != PS_TAX_EXC);
    
        if (count($products) > 20) {
            $full = false;
        }
        else {
            $full = true;

            foreach ($products as $index => $product) {
                if ($product instanceof Product) {
                    $product = (array)$product;
                }

                if (!isset($product['price'])) {
                    $product['price'] = (float)Tools::displayPrice(Product::getPriceStatic((int)$product['id_product'], $usetax), $currency);
                }
                $result_products[] = $this->wrapProduct($product, $extras, $index, $full);
            }
        }
	    
        return $result_products;
	}

	/**
	 * wrap product to provide a standard product information for google analytics script
	 */
	public function wrapProduct($product, $extras, $index = 0, $full = false)
	{
	    $hupi_product = '';
	    
	    $variant = null;
	    if (isset($product['attributes_small'])) {
	        $variant = $product['attributes_small'];
	    }
        elseif (isset($extras['attributes_small'])) {
	        $variant = $extras['attributes_small'];
        }
	    
        $product_qty = 1;
        if (isset($extras['qty'])) {
            $product_qty = $extras['qty'];
        }
	    elseif (isset($product['cart_quantity'])) {
            $product_qty = $product['cart_quantity'];
	    }
	    
        $product_id = 0;
        if (!empty($product['id_product'])) {
            $product_id = $product['id_product'];
        }
	    else if (!empty($product['id'])) {
            $product_id = $product['id'];
	    }
	    
//         if (!empty($product['id_product_attribute'])) {
//             $product_id .= '-'. $product['id_product_attribute'];
//         }
	    
        $product_type = 'typical';
        if (isset($product['pack']) && $product['pack'] == 1) {
            $product_type = 'pack';
        }
	    elseif (isset($product['virtual']) && $product['virtual'] == 1) {
            $product_type = 'virtual';
	    }
	    
	    if ($full) {
            $hupi_product = array(
                'id' => (string)$product_id,
                'name' => Tools::jsonEncode($product['name']),
                'category' => Tools::jsonEncode($product['category']),
                'brand' => isset($product['manufacturer_name']) ? Tools::jsonEncode($product['manufacturer_name']) : '',
                'variant' => Tools::jsonEncode($variant),
                'type' => $product_type,
                'position' => $index ? $index : '0',
                'quantity' => $product_qty,
                'list' => Tools::getValue('controller'),
                'url' => isset($product['link']) ? urlencode($product['link']) : '',
                'price' => number_format($product['price'], '2')
            );
        }
        else {
            $hupi_product = array(
                'id' => $product_id,
                'name' => Tools::jsonEncode($product['name'])
            );
        }
        return $hupi_product;
	}
    
	/**
     * add product impression js and product click js
     */
    public function addProductRecommendationImpression($products)
    {
        if (!is_array($products))
            return;
    
        $js = '';
        $js .= "console.log('setCustomVariable : products_recommendation => ".implode(',', $products)."');";
        $js .= 'Hupi.setCustomVariable('.Tools::jsonEncode(array('id' => 40, 'cvar_name' => 'products_recommendation', 'cvar_value' => array_values($products), 'scope' => 'page')).');';
        //$js .= 'Hupi.addProductImpression('.Tools::jsonEncode($product).",'',true);";

        return $js;
    }
    
    /**
     * add product impression js and product click js
     */
    public function addProductImpression($products)
    {
        if (!is_array($products))
            return;
    
        $js = '';
        $productsIds = array();
        foreach ($products as $product) {
//             $productsIds[] = $product['id'];
            self::$impressionProducts[] = $product['id'];
        }
//         $js .= "console.log('setCustomVariable : products_impression => ".implode(',', $productsIds)."');";
//         $js .= 'Hupi.setCustomVariable('.Tools::jsonEncode(array('id' => 30, 'cvar_name' => 'products_impression', 'cvar_value' => $productsIds, 'scope' => 'page')).');';
        //$js .= 'Hupi.addProductImpression('.Tools::jsonEncode($product).",'',true);";

        return $js;
    }
    
    /**
	 * add order transaction
	 */
	public function addTransaction($products, $order)
	{
	    if (!is_array($products))
	        return;
	
	        $js = '';
	        foreach ($products as $product)
	            $js .= 'Hupi.add('.Tools::jsonEncode($product).');';
	
	            return $js.'Hupi.addTransaction('.Tools::jsonEncode($order).');';
	}
	
	public function addProductClick($products)
	{
	    if (!is_array($products))
	        return;
	
        $js = '';
        foreach ($products as $product)
            $js .= 'Hupi.addProductClick('.Tools::jsonEncode($product).');';

        return $js;
	}
	
	public function addProductClickByHttpReferal($products)
	{
	    if (!is_array($products)) {
	        return;
	    }
	
        $js = '';
        foreach ($products as $product) {
            $js .= 'Hupi.addProductClickByHttpReferal('.Tools::jsonEncode($product).');';
        }

        return $js;
	}
	
	
	protected function filter($hupi_scripts)
	{
	    if ($this->filterable = 1)
	        return implode(';', array_unique(explode(';', $hupi_scripts)));
	
        return $hupi_scripts;
	}
	
	/**
	 * Add product checkout info
	 */
	public function addProductFromCheckout($products)
	{
	    if (!is_array($products))
	        return;
	
	        $js = '';
	        foreach ($products as $product)
	            $js .= 'Hupi.add('.Tools::jsonEncode($product).');';
	
	            return $js;
	}
	
	/**
	 * hook home to display generate the product list associated to home featured, news products and best sellers Modules
	 */
	public function isModuleEnabled($name)
	{
	    if (version_compare(_PS_VERSION_, '1.5', '>=')) {
	        if(Module::isEnabled($name)) {
	            $module = Module::getInstanceByName($name);
	            return ($module->isRegisteredInHook('home') || $module->isRegisteredInHook('displayHomeTabContent'));
	        }
    	    else {
	           return false;
    	    }
	    }
	    else {
            $module = Module::getInstanceByName($name);
            return ($module && $module->active === true);
        }
	}
	
	
    /**
     * Generate Google Analytics js
     */
    public function _runJs($js_code, $backoffice = 0)
    {
        if (Configuration::get('HUPI_ACCOUNT_ID'))
        {
            $runjs_code = '
                <script type="text/javascript">
                    var Hupi = HupilyticsEnhancedECommerce;
                    Hupi.setCustomVariable('.Tools::jsonEncode(array('id' => 1, 'cvar_name' => 'current_ts', 'scope' => 'page')).');
                    Hupi.setCustomVariable('.Tools::jsonEncode(array('id' => 2, 'cvar_name' => 'currency', 'cvar_value' => $this->context->currency->iso_code, 'scope' => 'page')).');
                ';
                if(Configuration::get('HUPIRECO_ACTIVE') == '1') {
            		$products = array_unique(self::$recommendedProducts);
            		if(count($products)) {
            		    $runjs_code .= $this->addProductRecommendationImpression($products);
            		}
        		}
    		$runjs_code .= '</script>';
            
            if (!empty($js_code)) {
                $runjs_code .= '
                <script type="text/javascript">
                    '.$js_code.'
                </script>';
            }
//				pk_id = null;
//              _paq.push([ function() { pk_id = this.getVisitorId(); }]);

            if($arrPkId = preg_grep( "#_pk_id_#", array_keys($_COOKIE))) {
                $this->context->cookie->__set('pk_id' , $_COOKIE[array_pop($arrPkId)]);
            }
            
            if (($this->js_state) != 1 && ($backoffice == 0)) {
                $runjs_code .= '
			<script type="text/javascript">
                console.log(\'trackPageView\');
                _paq.push([\'trackPageView\']);
                    
                console.log(\'enableLinkTracking\');
                _paq.push([\'enableLinkTracking\']);
			</script>';
            }

            return $runjs_code;
        }
    }
    
    protected function _getHupilyticsTag($back_office = false)
    {
        $user_id = null;
        if (Configuration::get('HUPI_USERID_ENABLED') &&
            $this->context->customer && $this->context->customer->isLogged()
            ){
                $user_id = (int)$this->context->customer->id;
        }
    
        return '
            <script type="text/javascript">
              var _paq = _paq || [];
              (function()
              {
                var u = "https://api.catchbox.hupi.io/v2/'.Tools::safeOutput(Configuration::get('HUPI_ACCOUNT_ID')).'/hupilytics";
                _paq.push([\'setTrackerUrl\', u]);  // Required
                _paq.push([\'setSiteId\', '.(int)Tools::safeOutput(Configuration::get('HUPI_SITE_ID')).']);  // Required: must be an integer
                '.
                ($user_id?'_paq.push([\'setUserId\', \''.$user_id.'\']);':'')
                .'
                var d=document, g=d.createElement(\'script\'), s=d.getElementsByTagName(\'script\')[0];
                g.type=\'text/javascript\';
                g.defer=true;
                g.async=true;
                g.src=u;
                s.parentNode.insertBefore(g,s);
              })();
            </script>';
    }
    
    
    /**
     * Hook admin office header to add google analytics js
     */
    public function hookActionProductCancel($params)
    {
        $qty_refunded = Tools::getValue('cancelQuantity');
        $ga_scripts = '';
        foreach ($qty_refunded as $orderdetail_id => $qty)
        {
            // Display GA refund product
            $order_detail = new OrderDetail($orderdetail_id);
            $hupi_scripts .= 'Hupi.add('.Tools::jsonEncode(
                array(
                    'id' => empty($order_detail->product_attribute_id)?$order_detail->product_id:$order_detail->product_id.'-'.$order_detail->product_attribute_id,
                    'quantity' => $qty)
                ).');';
        }
        $this->context->cookie->hupi_admin_refund = $hupi_scripts.'Hupi.refundByProduct('.Tools::jsonEncode(array('id' => $params['order']->id)).');';
    }
    
    public function hookActionCartSave()
    {
		if (!isset($this->context->cart))
			return;

		if (!Tools::getIsset('id_product'))
			return;

		$cart = array(
			'controller' => Tools::getValue('controller'),
			'addAction' => Tools::getValue('add') ? 'add' : '',
			'removeAction' => Tools::getValue('delete') ? 'delete' : '',
			'extraAction' => Tools::getValue('op'),
			'qty' => (int)Tools::getValue('qty', 1)
		);

		$cart_products = $this->context->cart->getProducts();
		if (isset($cart_products) && count($cart_products))
			foreach ($cart_products as $cart_product)
				if ($cart_product['id_product'] == Tools::getValue('id_product'))
					$add_product = $cart_product;

		if ($cart['removeAction'] == 'delete')
		{
			$add_product_object = new Product((int)Tools::getValue('id_product'), true, (int)Configuration::get('PS_LANG_DEFAULT'));
			if (Validate::isLoadedObject($add_product_object))
			{
				$add_product['name'] = $add_product_object->name;
				$add_product['manufacturer_name'] = $add_product_object->manufacturer_name;
				$add_product['category'] = $add_product_object->category;
				$add_product['reference'] = $add_product_object->reference;
				$add_product['link_rewrite'] = $add_product_object->link_rewrite;
				$add_product['link'] = $add_product_object->link_rewrite;
				$add_product['price'] = $add_product_object->price;
				$add_product['ean13'] = $add_product_object->ean13;
				$add_product['id_product'] = Tools::getValue('id_product');
				$add_product['id_category_default'] = $add_product_object->id_category_default;
				$add_product['out_of_stock'] = $add_product_object->out_of_stock;
				$add_product = Product::getProductProperties((int)Configuration::get('PS_LANG_DEFAULT'), $add_product);
			}
		}

		if (isset($add_product) && !in_array((int)Tools::getValue('id_product'), self::$products))
		{
			self::$products[] = (int)Tools::getValue('id_product');
			$hupi_products = $this->wrapProduct($add_product, $cart, 0, true);

			if (array_key_exists('id_product_attribute', $hupi_products) && $hupi_products['id_product_attribute'] != '' && $hupi_products['id_product_attribute'] != 0)
				$id_product = $hupi_products['id_product_attribute'];
			else
				$id_product = Tools::getValue('id_product');

			if (isset($this->context->cookie->hupi_cart))
				$hupicart = unserialize($this->context->cookie->hupi_cart);
			else
				$hupicart = array();

			if ($cart['removeAction'] == 'delete')
				$hupi_products['quantity'] = -1;
			elseif ($cart['extraAction'] == 'down')
			{
				if (array_key_exists($id_product, $hupicart))
					$hupi_products['quantity'] = $hupicart[$id_product]['quantity'] - $cart['qty'];
				else
					$hupi_products['quantity'] = $cart['qty'] * -1;
			}
			elseif (Tools::getValue('step') <= 0) // Sometimes cartsave is called in checkout
			{
				if (array_key_exists($id_product, $hupicart))
					$hupi_products['quantity'] = $hupicart[$id_product]['quantity'] + $cart['qty'];
			}

			$hupicart[$id_product] = $hupi_products;
			$this->context->cookie->hupi_cart = serialize($hupicart);
		}
    }

    public function hookDisplayAdminOrderContentOrder()
    {
		echo $this->_runJs($this->context->cookie->hupi_admin_refund, 1);
		unset($this->context->cookie->hupi_admin_refund);
    }

    public function hookDisplayFooter()
    {
		$hupi_scripts = '';
		$this->js_state = 0;

		if (isset($this->context->cookie->hupi_cart))
		{
			$this->filterable = 0;

			$hupicarts = unserialize($this->context->cookie->hupi_cart);
			foreach ($hupicarts as $hupicart)
			{
				if ($hupicart['quantity'] > 0) {
					$hupi_scripts .= 'Hupi.addToCart('.Tools::jsonEncode($hupicart).');';
				} elseif ($hupicart['quantity'] < 0) {
					$hupicart['quantity'] = abs($hupicart['quantity']);
					$hupi_scripts .= 'Hupi.removeFromCart('.Tools::jsonEncode($hupicart).');';
				}
				$hupi_scripts .= 'Hupi.trackCartUpdate('.$this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS).');';
			}
			unset($this->context->cookie->hupi_cart);
		}

		$controller_name = Tools::getValue('controller');
		$products = $this->wrapProducts($this->context->smarty->getTemplateVars('products'), array(), true);

		if ($controller_name == 'product')
		{
		    // Add product view
		    $product = new Product(Tools::getValue('id_product'));
		    $hupi_product = $this->wrapProduct((array)$product, null, 0, true);
		    $hupi_scripts .= 'Hupi.addProductDetailView('.Tools::jsonEncode($hupi_product).');';
		
		    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) > 0)
		        $hupi_scripts .= $this->addProductClickByHttpReferal(array($hupi_product));
		
		        $this->js_state = 1;
		}
		
		if ($controller_name == 'order' || $controller_name == 'orderopc')
		{
			$this->eligible = 1;
			$step = Tools::getValue('step');
			if (empty($step))
				$step = 0;
			$hupi_scripts .= $this->addProductFromCheckout($products, $step);
			$hupi_scripts .= 'Hupi.addCheckout(\''.(int)$step.'\');';
		}

		if (version_compare(_PS_VERSION_, '1.5', '<'))
		{
			if ($controller_name == 'orderconfirmation')
				$this->eligible = 1;
		}
		else
		{
			$confirmation_hook_id = (int)Hook::getIdByName('orderConfirmation');
			if (isset(Hook::$executed_hooks[$confirmation_hook_id]))
				$this->eligible = 1;
		}

		if (isset($products) && count($products) && $controller_name != 'index')
		{
			if ($this->eligible == 0) {
				$hupi_scripts .= $this->addProductImpression($products);
			}
			$hupi_scripts .= $this->addProductClick($products);
		} 
		
		if(count(self::$impressionProducts)) {
            $hupi_scripts .= "console.log('setCustomVariable : products_impression => ".implode(',', array_unique(self::$impressionProducts))."');";
            $hupi_scripts .= 'Hupi.setCustomVariable('.Tools::jsonEncode(array('id' => 30, 'cvar_name' => 'products_impression', 'cvar_value' => array_values(array_unique(self::$impressionProducts)), 'scope' => 'page')).');';
		}
		
		if(Configuration::get('HUPIRECO_ACTIVE') == '1') {
    		$hupi_scripts .= 'Hupi.addProductRecommendationClick();';
		}

		return $this->_runJs($hupi_scripts);
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $order = $params['objOrder'];
		if (Validate::isLoadedObject($order) && $order->getCurrentState() != (int)Configuration::get('PS_OS_ERROR'))
		{
			$ga_order_sent = Db::getInstance()->getValue('SELECT id_order FROM `'._DB_PREFIX_.'hupilytics` WHERE id_order = '.(int)$order->id);
			if ($ga_order_sent === false)
			{
				Db::getInstance()->Execute('INSERT INTO `'._DB_PREFIX_.'hupilytics` (id_order, id_shop, sent, date_add) VALUES ('.(int)$order->id.', '.(int)$this->context->shop->id.', 0, NOW())');
				if ($order->id_customer == $this->context->cookie->id_customer)
				{
					$order_products = array();
					$cart = new Cart($order->id_cart);
					foreach ($cart->getProducts() as $order_product)
						$order_products[] = $this->wrapProduct($order_product, array(), 0, true);

					$transaction = array(
						'id' => $order->id,
						'affiliation' => (version_compare(_PS_VERSION_, '1.5', '>=') && Shop::isFeatureActive()) ? $this->context->shop->name : Configuration::get('PS_SHOP_NAME'),
						'total_tax_incl' => $order->total_paid_tax_incl,
						'total_tax_excl' => $order->total_paid_tax_excl,
						'tax' => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
						'shipping' => $order->total_shipping_tax_incl,
						'url' => $this->context->link->getModuleLink('ganalytics', 'ajax', array(), true),
						'customer' => $order->id_customer);
					$ga_scripts = $this->addTransaction($order_products, $transaction);

					$this->js_state = 1;
					return $this->_runJs($ga_scripts);
				}
			}
		}
    }
    
    public function hookProductTab($params)
    {
        if(Configuration::get('HUPIRECO_ACTIVE') != 1 || Configuration::get('HUPIRECO_PROD_ACTIVE') != '1' || !Configuration::get('HUPIRECO_PROD_ENDPOINT')) {
            return;
        }
    
        $nbProd = (int)Configuration::get('HUPIRECO_PROD_NB');
        if($nbProd == 0) {
            $nbProd = null;
        }
    
        $id_product = Tools::getValue('id_product');
        if($products = $this->getProducts(Configuration::get('HUPIRECO_PROD_ENDPOINT'), $nbProd, $id_product)) {
            $this->smarty->assign('products', $products);
    
            if (version_compare(_PS_VERSION_, '1.6', '>=') && !(bool)Configuration::get('HUPIRECO_PROD_HASTAB')) {
                return $this->display(__FILE__, 'tab-name.tpl');
            } else {
                return $this->display(__FILE__, 'tab-name-1.5.tpl');
            }
        }
    }
    
    public function hookProductTabContent($params)
    {
        if(Configuration::get('HUPIRECO_ACTIVE') != 1 || Configuration::get('HUPIRECO_PROD_ACTIVE') != '1' || !Configuration::get('HUPIRECO_PROD_ENDPOINT')) {
            return;
        }
        $nbProd = (int)Configuration::get('HUPIRECO_PROD_NB');
        if($nbProd == 0) {
            $nbProd = null;
        }
    
        $id_product = Tools::getValue('id_product');
        if($products = $this->getProducts(Configuration::get('HUPIRECO_PROD_ENDPOINT'), $nbProd, $id_product)) {
            $this->smarty->assign(array(
                'products' => $products,
                'endpoint' => Configuration::get('HUPIRECO_PROD_ENDPOINT')
            ));
    
            foreach ($products as $product) {
                self::$recommendedProducts[] = $product['id_product'];
            }
            if (version_compare(_PS_VERSION_, '1.6', '>=') && !(bool)Configuration::get('HUPIRECO_PROD_HASTAB')) {
                return $this->display(__FILE__, 'tab-content.tpl');
            } else {
                return $this->display(__FILE__, 'tab-content-1.5.tpl');
            }
        }
    }
    
    public function hookProductFooter($params)
    {
        return $this->hookProductTabContent($params);
    }
    
    
    public function hookDisplayHomeTab($params)
    {
        if(Configuration::get('HUPIRECO_ACTIVE') != 1 || Configuration::get('HUPIRECO_HP_ACTIVE') != '1' || !Configuration::get('HUPIRECO_HP_ENDPOINT')) {
            return;
        }
    
        $nbProd = (int)Configuration::get('HUPIRECO_HP_NB');
        if($nbProd == 0) {
            $nbProd = null;
        }
    
        if($products = $this->getProducts(Configuration::get('HUPIRECO_HP_ENDPOINT'), $nbProd)) {
            $this->smarty->assign('products', $products);
    
            return $this->display(__FILE__, 'home-tab-name.tpl');
        }
    }
    
    public function hookDisplayHome($params)
    {
        $hupi_scripts = '';
        
        // Home featured products
        if ($this->isModuleEnabled('homefeatured'))
        {
            $category = new Category($this->context->shop->getCategory(), $this->context->language->id);
            $home_featured_products = $this->wrapProducts($category->getProducts((int)Context::getContext()->language->id, 1,
                (Configuration::get('HOME_FEATURED_NBR') ? (int)Configuration::get('HOME_FEATURED_NBR') : 8), 'position'), array(), true);
            $hupi_scripts .= $this->addProductImpression($home_featured_products).$this->addProductClick($home_featured_products);
        }
        
        // New products
        if ($this->isModuleEnabled('blocknewproducts') && (Configuration::get('PS_NB_DAYS_NEW_PRODUCT')
            || Configuration::get('PS_BLOCK_NEWPRODUCTS_DISPLAY')))
        {
            $new_products = Product::getNewProducts((int)$this->context->language->id, 0, (int)Configuration::get('NEW_PRODUCTS_NBR'));
            $new_products_list = $this->wrapProducts($new_products, array(), true);
            $hupi_scripts .= $this->addProductImpression($new_products_list).$this->addProductClick($new_products_list);
        }
        
        // Best Sellers
        if ($this->isModuleEnabled('blockbestsellers') && (!Configuration::get('PS_CATALOG_MODE')
            || Configuration::get('PS_BLOCK_BESTSELLERS_DISPLAY')))
        {
            $hupi_homebestsell_product_list = $this->wrapProducts(ProductSale::getBestSalesLight((int)$this->context->language->id, 0, 8), array(), true);
            $hupi_scripts .= $this->addProductImpression($hupi_homebestsell_product_list).$this->addProductClick($hupi_homebestsell_product_list);
        }
        
        $this->js_state = 1;
        
        $this->_runJs($this->filter($hupi_scripts));
        
        
        if(Configuration::get('HUPIRECO_ACTIVE') != 1 || Configuration::get('HUPIRECO_HP_ACTIVE') != '1' || !Configuration::get('HUPIRECO_HP_ENDPOINT')) {
            return;
        }
        $nbProd = (int)Configuration::get('HUPIRECO_HP_NB');
        if($nbProd == 0) {
            $nbProd = null;
        }
    
        if($products = $this->getProducts(Configuration::get('HUPIRECO_HP_ENDPOINT'), $nbProd)) {
            $this->smarty->assign(array(
                'products' => $products,
                'endpoint' => Configuration::get('HUPIRECO_HP_ENDPOINT')
            ));
    
            foreach ($products as $product) {
                self::$recommendedProducts[] = $product['id_product'];
            }
            return $this->display(__FILE__, 'home-tab-content.tpl');
        }
    }
    
    public function hookDisplayHomeTabContent($params)
    {
        return $this->hookDisplayHome($params);
    }
    
    public function hookdisplayHupiRecommendations($params)
    {
        if(Configuration::get('HUPIRECO_ACTIVE') != 1) {
            return;
        }
        
        if(isset($params['productId']) && $params['productId']) {
            $id_product = $params['productId'];
        } else {
            return ;
        }
    
        if(isset($params['nbItems']) && $params['nbItems']) {
            $nbProd = (int)$params['nbItems'];
        } else {
            $nbProd = (int)Configuration::get('HUPIRECO_PROD_NB');
        }
        if($nbProd == 0) {
            $nbProd = null;
        }
    
        if($products = $this->getProducts(Configuration::get('HUPIRECO_PROD_ENDPOINT'), $nbProd, $id_product)) {
            $this->smarty->assign(array(
                'products' => $products,
                'endpoint' => Configuration::get('HUPIRECO_PROD_ENDPOINT')
            ));
    
            foreach ($products as $product) {
                self::$recommendedProducts[] = $product['id_product'];
            }
            return $this->display(__FILE__, 'display-recommendation.tpl');
        }
        return ;
    }
    
    public function hookDisplayShoppingCart($params)
    {
        if(Configuration::get('HUPIRECO_ACTIVE') != 1 || Configuration::get('HUPIRECO_CART_ACTIVE') != '1' || !Configuration::get('HUPIRECO_CART_ENDPOINT')) {
            return;
        }
    
        $nbProd = (int)Configuration::get('HUPIRECO_CART_NB');
        if($nbProd == 0) {
            $nbProd = null;
        }
    
    
        if($products = $this->getProducts(Configuration::get('HUPIRECO_CART_ENDPOINT'), $nbProd)) {
            $this->context->controller->addCss(_THEME_CSS_DIR_.'product_list.css', 'all');
            $this->smarty->assign(array(
                'products' => $products,
                'endpoint' => Configuration::get('HUPIRECO_CART_ENDPOINT')
            ));
    
            foreach ($products as $product) {
                self::$recommendedProducts[] = $product['id_product'];
            }
            return $this->display(__FILE__, 'shopping-cart.tpl');
        }
    
    }
}
