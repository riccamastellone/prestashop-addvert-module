<?php
/**
 * @package  Addvert
 * @author   Gennaro Vietri <gennaro.vietri@gmail.com>
 */
if (!defined('_PS_VERSION_'))
    exit;

class Addvert extends Module
{
    const SCRIPT_BASE_URL = 'http://addvert.it';

    public $ecommerceId;

    public $secretKey;

    public $buttonLayout;

    protected $_tags = null;

    protected $_categories = null;

    const ADDVERT_TYPE = 'product';

    protected function _getProduct()
    {
        $product = null;

        if ($id_product = (int)Tools::getValue('id_product')) {
            $product = new Product($id_product, true, $this->context->language->id);

            if (!Validate::isLoadedObject($product)) $product = null;
        }

        return $product;
    }

    public function __construct()
    {
        $this->name = 'addvert';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0';
        $this->author = 'Gennaro Vietri';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Addvert integration');
        $this->description = $this->l('PrestaShop Module to integrate Addvert affiliation platform.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->initialize();
    }

    public function install()
    {
        Configuration::updateValue('ADDVERT_ECOMMERCE_ID', $this->ecommerceId);
        Configuration::updateValue('ADDVERT_SECRET_KEY', $this->secretKey);
        Configuration::updateValue('ADDVERT_BUTTON_LAYOUT', $this->buttonLayout);

        return (parent::install()
            && $this->registerHook('header')
            && $this->registerHook('productActions')
            && $this->registerHook('orderConfirmation'));
    }

    public function uninstall()
    {
        Configuration::deleteByName('ADDVERT_ECOMMERCE_ID');
        Configuration::deleteByName('ADDVERT_SECRET_KEY');
        Configuration::deleteByName('ADDVERT_BUTTON_LAYOUT');
        return parent::uninstall();
    }

    protected function initialize()
    {
        $this->ecommerceId = htmlentities(Configuration::get('ADDVERT_ECOMMERCE_ID'), ENT_QUOTES, 'UTF-8');
        $this->secretKey = htmlentities(Configuration::get('ADDVERT_SECRET_KEY'), ENT_QUOTES, 'UTF-8');
        $this->buttonLayout = htmlentities(Configuration::get('ADDVERT_BUTTON_LAYOUT'), ENT_QUOTES, 'UTF-8');

        // Retrocompatibility
        $this->initContext();
    }

    public function postProcess()
    {
        $errors = '';
        if (Tools::isSubmit('submitAddvertConf'))
        {
            if ($ecommerceId = Tools::getValue('ecommerce_id')) {
                Configuration::updateValue('ADDVERT_ECOMMERCE_ID', $ecommerceId);
            } elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP) {
                Configuration::deleteFromContext('ADDVERT_ECOMMERCE_ID');
            }

            if ($secretKey = Tools::getValue('secret_key'))
                Configuration::updateValue('ADDVERT_SECRET_KEY', $secretKey);
            elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP)
                Configuration::deleteFromContext('ADDVERT_SECRET_KEY');

            if ($buttonLayout = Tools::getValue('button_layout'))
                Configuration::updateValue('ADDVERT_BUTTON_LAYOUT', $buttonLayout);
            elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP)
                Configuration::deleteFromContext('ADDVERT_BUTTON_LAYOUT');

            $this->initialize();
        }
        if ($errors)
            echo $this->displayError($errors);
    }

    public function getContent()
    {
        $this->postProcess();
        $output = '
		<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post" enctype="multipart/form-data">
			<fieldset>
				<legend>'.$this->l('Addvert integration configuration').'</legend>
				<br/><br/>
				<label for="ecommerce_id">'.$this->l('Ecommerce ID').'</label>
				<div class="margin-form">
					<input id="ecommerce_id" type="text" name="ecommerce_id" value="'.$this->ecommerceId.'" style="width:250px" />
				</div>
				<br class="clear"/>
				<label for="secret_key">'.$this->l('Secret key').'</label>
				<div class="margin-form">
					<input id="secret_key" type="text" name="secret_key" value="'.$this->secretKey.'" style="width:250px" />
				</div>
				<br class="clear"/>
				<label for="button_layout">'.$this->l('Button layout').'</label>
				<div class="margin-form">
					<select id="secret_key" name="button_layout">
					    <option value="standard"' . ($this->buttonLayout == 'standard' ? ' selected="selected"' : '') . '>Standard</option>
					    <option value="small"' . ($this->buttonLayout == 'small' ? ' selected="selected"' : '') . '>Small</option>
					</select>
				</div>
				<br class="clear"/>
				<div class="margin-form">
					<input class="button" type="submit" name="submitAddvertConf" value="'.$this->l('Validate').'"/>
				</div>
				<br class="clear"/>
			</fieldset>
		</form>';
        return $output;
    }

    /**
     * Costruisce i meta per l'head della scheda prodotto
     */
    public function getMetaHtml()
    {
        $metaHtml = '';

        if ($this->_isProductPage()) {
            $product = $this->_getProduct();

            $metas = array(
                array('property' => 'og:url',           'content' => $product->getLink()),
                array('property' => 'og:title',         'content' => $product->name),
                array('property' => 'og:description',   'content' => strip_tags($product->description_short)),
                array('name' => 'addvert:type',         'content' => self::ADDVERT_TYPE),
                array('name' => 'addvert:ecommerce_id', 'content' => $this->ecommerceId),
                array('name' => 'addvert:price',        'content' => number_format($product->getPrice(), 2, '.', '')),
            );

            $image = Product::getCover($product->id);
            if (isset($image['id_image'])) {
                $metas[] = array('property' => 'og:image', 'content' => $this->context->link->getImageLink($product->link_rewrite, $image['id_image']));
            }

            if ($categoryId = $this->getDefaultCategory($product)) {
                $category = new Category($categoryId);
                $metas[] = array('name' => 'addvert:category', 'content' => $category->getName());
            }

            foreach (explode(',', $product->getTags($this->context->language->id)) as $tag) {
                $metas[] = array('name' => 'addvert:tag', 'content' => trim($tag));
            }

            foreach ($metas as $meta) {
                $metaHtml .= '<meta';
                foreach ($meta as $attribute => $value) {
                    $metaHtml .= sprintf(' %s="%s"', $attribute, $this->escapeHtml($value));
                }
                $metaHtml .= ' />' . "\n";
            }
        }

        return $metaHtml;
    }

    public function hookHeader()
    {
        if ($this->_isProductPage()) {
            return $this->getMetaHtml();
        } else {
            return '';
        }
    }

    public function getButtonHtml()
    {
        return '
<script type="text/javascript">
    (function() {
        var js = document.createElement(\'script\'); js.type = \'text/javascript\'; js.async = true;
        js.src = \'' . $this->getScriptUrl() . '\';
        var s = document.getElementsByTagName(\'script\')[0]; s.parentNode.insertBefore(js, s);
    })();
</script>
<div class="addvert-btn" data-width="450" data-layout="' . $this->buttonLayout . '"></div>
        ';
    }

    public function hookProductActions()
    {
        return $this->getButtonHtml();
    }

    public function getOrderTrakingHtml($orderId, $orderTotal)
    {
        $url = sprintf(self::SCRIPT_BASE_URL . '/api/order/prep_total?ecommerce_id=%s&secret=%s&tracking_id=%s&total=%s', $this->ecommerceId, $this->secretKey, $orderId, $orderTotal);
        $orderKey = file_get_contents($url);

        return '<script src="' . self::SCRIPT_BASE_URL . '/api/order/send_total?key=' . $orderKey . '"></script>';
    }

    public function hookOrderConfirmation($params)
    {
        return $this->getOrderTrakingHtml($params['objOrder']->id, $params['total_to_pay']);
    }

    public function escapeHtml($data, $allowedTags = null)
    {
        if (is_array($data)) {
            $result = array();
            foreach ($data as $item) {
                $result[] = $this->escapeHtml($item);
            }
        } else {
            // process single item
            if (strlen($data)) {
                if (is_array($allowedTags) and !empty($allowedTags)) {
                    $allowed = implode('|', $allowedTags);
                    $result = preg_replace('/<([\/\s\r\n]*)(' . $allowed . ')([\/\s\r\n]*)>/si', '##$1$2$3##', $data);
                    $result = htmlspecialchars($result, ENT_COMPAT, 'UTF-8', false);
                    $result = preg_replace('/##([\/\s\r\n]*)(' . $allowed . ')([\/\s\r\n]*)##/si', '<$1$2$3>', $result);
                } else {
                    $result = htmlspecialchars($data, ENT_COMPAT, 'UTF-8', false);
                }
            } else {
                $result = $data;
            }
        }
        return $result;
    }

    public function getScriptUrl()
    {
        return self::SCRIPT_BASE_URL . '/api/js/addvert-btn.js';
    }

    protected function _isProductPage()
    {
        return ((int)Tools::getValue('id_product') > 0);
    }

    private function initContext()
    {
        if (class_exists('Context')) {
            $this->context = Context::getContext();
        } else {
            global $smarty, $cookie, $link;
            $this->context = new StdClass();
            $this->context->smarty = $smarty;
            $this->context->cookie = $cookie;
            $this->context->link = $link;
            $this->context->language = new Language($cookie->id_lang);
        }
    }

    public function getDefaultCategory($product)
    {
        if (method_exists($product, 'getDefaultCategory')) {
            return $product->getDefaultCategory();
        } else {
            $default_category = Db::getInstance()->getValue('
                SELECT p.`id_category_default`
                FROM `'._DB_PREFIX_.'product` p
                WHERE p.`id_product` = '.(int)$product->id);

            return $default_category;
        }
    }
}
