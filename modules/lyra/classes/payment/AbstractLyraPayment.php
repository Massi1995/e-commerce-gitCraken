<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for PrestaShop. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (! defined('_PS_VERSION_')) {
    exit;
}

abstract class AbstractLyraPayment
{
    const LYRA_CART_MAX_NB_PRODUCTS = 85;

    protected $prefix;
    protected $tpl_name;
    protected $logo;
    protected $name;

    protected $currencies = array();
    protected $countries = array();
    protected $needs_cart_data = false;
    protected $force_local_cart_data = false;

    public function isAvailable($cart)
    {
        if (! $this->checkActive()) {
            return false;
        }

        if (! $this->checkAmountRestriction($cart)) {
            return false;
        }

        if (! $this->checkCurrency($cart)) {
            return false;
        }

        if (! $this->checkCountry($cart)) {
            return false;
        }

        return true;
    }

    protected function checkActive()
    {
        return Configuration::get($this->prefix . 'ENABLED') === 'True';
    }

    protected function checkAmountRestriction($cart)
    {
        $config_options = @unserialize(Configuration::get($this->prefix . 'AMOUNTS'));
        if (! is_array($config_options) || empty($config_options)) {
            return true;
        }

        $customer_group = (int) Customer::getDefaultGroupId($cart->id_customer);

        $all_min_amount = $config_options[0]['min_amount'];
        $all_max_amount = $config_options[0]['max_amount'];

        $min_amount = null;
        $max_amount = null;
        foreach ($config_options as $key => $value) {
            if (empty($value) || $key === 0) {
                continue;
            }

            if ($key === $customer_group) {
                $min_amount = $value['min_amount'];
                $max_amount = $value['max_amount'];

                break;
            }
        }

        if (! is_numeric($min_amount)) {
            $min_amount = $all_min_amount;
        }

        if (! is_numeric($max_amount)) {
            $max_amount = $all_max_amount;
        }

        $amount = $cart->getOrderTotal();

        if ((is_numeric($min_amount) && $amount < $min_amount) || (is_numeric($max_amount) && $amount > $max_amount)) {
            return false;
        }

        return true;
    }

    protected function checkCurrency($cart)
    {
        if (! is_array($this->currencies) || empty($this->currencies)) {
            return true;
        }

        // Check if submodule is available for some currencies.
        $cart_currency = new Currency((int) $cart->id_currency);
        if (in_array($cart_currency->iso_code, $this->currencies)) {
            return true;
        }

        return false;
    }

    protected function checkCountry($cart)
    {
        $billing_address = new Address((int) $cart->id_address_invoice);
        $billing_country = new Country((int) $billing_address->id_country);

        // Submodule country restriction.
        $submoduleAvailableCountries = true;
        if (is_array($this->countries) && ! empty($this->countries)) {
            $submoduleAvailableCountries = in_array($billing_country->iso_code, $this->countries);
        }

        // Backend restriction on countries.
        $backendAllowAllCountries = Configuration::get($this->prefix . 'COUNTRY') === '1' ? true : false;
        $backendAllowSpecificCountries = ! Configuration::get($this->prefix . 'COUNTRY_LST') ?
            array() : explode(';', Configuration::get($this->prefix . 'COUNTRY_LST'));

        if ($backendAllowAllCountries) {
            if ($submoduleAvailableCountries) {
                return true;
            }
        } elseif (in_array($billing_country->iso_code, $backendAllowSpecificCountries) && $submoduleAvailableCountries) {
            return true;
        }

        return false;
    }

    protected function proposeOney($data = array())
    {
        return false;
    }

    protected function isOney34()
    {
        return false;
    }

    public function validate($cart, $data = array())
    {
        $errors = array();
        return $errors;
    }

    public function getTplName()
    {
        return $this->tpl_name;
    }

    public function getLogo()
    {
        return $this->logo;
    }

    public function getTplVars($cart)
    {
        return array(
            'lyra_title' => $this->getTitle((int) $cart->id_lang),
            'lyra_logo' => _MODULE_DIR_ . 'lyra/views/img/' . $this->getLogo()
        );
    }

    public function getPaymentOption($cart)
    {
        $class_name = '\PrestaShop\PrestaShop\Core\Payment\PaymentOption';
        $option = new $class_name();
        $option->setCallToActionText($this->getTitle((int) $cart->id_lang))
                ->setModuleName('lyra')
                ->setLogo(_MODULE_DIR_ . 'lyra/views/img/' . $this->getLogo());

        if (! $this->hasForm()) {
            $option->setAction(Context::getContext()->link->getModuleLink('lyra', 'redirect', array(), true));

            $inputs = array(
                array('type' => 'hidden', 'name' => 'lyra_payment_type', 'value' => $this->name)
            );
            $option->setInputs($inputs);
        }

        return $option;
    }

    public function getTitle($lang)
    {
        $title = Configuration::get($this->prefix . 'TITLE', $lang);
        if (! $title) {
            $title = $this->getDefaultTitle();
        }

        return $title;
    }

    public function hasForm()
    {
        return false;
    }

    abstract protected function getDefaultTitle();

    /**
     * Generate form fields to post to the payment gateway.
     *
     * @param Cart $cart
     * @param array[string][string] $data
     * @return array[string][string]
     */
    public function prepareRequest($cart, $data = array())
    {
        // Update shop info in cart to avoid errors when shopping cart is shared.
        $shop = Context::getContext()->shop;
        if ($shop->getGroup()->share_order && ($cart->id_shop != $shop->id)) {
            $cart->id_shop = $shop->id;
            $cart->id_shop_group = $shop->id_shop_group;
            $cart->save();
        }

        /* @var $billing_country Address */
        $billing_address = new Address((int) $cart->id_address_invoice);
        $billing_country = new Country((int) $billing_address->id_country);

        /* @var $delivery_address Address */
        $colissimo_address = LyraTools::getColissimoDeliveryAddress($cart); // Get SoColissimo delivery address.
        if ($colissimo_address instanceof Address) {
            $delivery_address = $colissimo_address;
        } else {
            $delivery_address = new Address((int) $cart->id_address_delivery);
        }

        $delivery_country = new Country((int) $delivery_address->id_country);

        LyraTools::getLogger()->logInfo("Form data generation for cart #{$cart->id} with {$this->name} submodule.");

        require_once _PS_MODULE_DIR_ . 'lyra/classes/LyraRequest.php';
        /* @var $request LyraRequest */
        $request = new LyraRequest();

        $contrib = LyraTools::getDefault('CMS_IDENTIFIER') . '_' . LyraTools::getDefault('PLUGIN_VERSION') . '/' . _PS_VERSION_ . '/' . PHP_VERSION;
        if (defined('_PS_HOST_MODE_')) {
            $contrib = str_replace('PrestaShop', 'PrestaShop_Cloud', $contrib);
        }

        $request->set('contrib', $contrib);

        foreach (LyraTools::getAdminParameters() as $param) {
            if (isset($param['name'])) {
                $id_lang = null;
                if (in_array($param['key'], LyraTools::$multi_lang_fields)) {
                    $id_lang = (int) $cart->id_lang;
                }

                $value = Configuration::get($param['key'], $id_lang);

                if (($param['name'] !== 'theme_config') || ($value !== 'RESPONSIVE_MODEL=')) {
                    // Set payment gateway params only.
                    $request->set($param['name'], $value);
                }
            }
        }

        // Detect default language.
        /* @var $language Language */
        $language = Language::getLanguage((int) $cart->id_lang);
        $language_iso_code = $language['language_code'] ?
            Tools::substr($language['language_code'], 0, 2) : $language['iso_code'];
        $language_iso_code = Tools::strtolower($language_iso_code);
        if (! LyraApi::isSupportedLanguage($language_iso_code)) {
            $language_iso_code = Configuration::get('LYRA_DEFAULT_LANGUAGE');
        }

        // Detect store currency.
        $cart_currency = new Currency((int) $cart->id_currency);
        $currency = LyraApi::findCurrencyByAlphaCode($cart_currency->iso_code);

        // Amount rounded to currency decimals.
        $amount = Tools::ps_round($cart->getOrderTotal(), $currency->getDecimals());

        $request->set('amount', $currency->convertAmountToInteger($amount));
        $request->set('currency', $currency->getNum());
        $request->set('language', $language_iso_code);
        $request->set('order_id', $cart->id);

        /* @var $cust Customer */
        $cust = new Customer((int) $cart->id_customer);

        // Customer data.
        $request->set('cust_email', $cust->email);
        $request->set('cust_id', $cust->id);

        $cust_title = new Gender((int) $cust->id_gender);
        $request->set('cust_title', $cust_title->name[Context::getContext()->language->id]);

        $phone = $billing_address->phone ? $billing_address->phone : $billing_address->phone_mobile;
        $cell_phone = $billing_address->phone_mobile ? $billing_address->phone_mobile : $billing_address->phone;

        $request->set('cust_first_name', $billing_address->firstname);
        $request->set('cust_last_name', $billing_address->lastname);
        $request->set('cust_legal_name', $billing_address->company ? $billing_address->company : null);
        $request->set('cust_address', $billing_address->address1 . ' ' . $billing_address->address2);
        $request->set('cust_zip', $billing_address->postcode);
        $request->set('cust_city', $billing_address->city);
        $request->set('cust_phone', $phone);
        $request->set('cust_cell_phone', $cell_phone);
        $request->set('cust_country', $billing_country->iso_code);
        if ($billing_address->id_state) {
            $state = new State((int) $billing_address->id_state);
            $request->set('cust_state', $state->iso_code);
        }

        if (! $cart->isVirtualCart() && ($delivery_address instanceof Address)) {
            $request->set('ship_to_first_name', $delivery_address->firstname);
            $request->set('ship_to_last_name', $delivery_address->lastname);
            $request->set('ship_to_legal_name', $delivery_address->company ? $delivery_address->company : null);
            $request->set('ship_to_street', $delivery_address->address1);
            $request->set('ship_to_street2', $delivery_address->address2);
            $request->set('ship_to_zip', $delivery_address->postcode);
            $request->set('ship_to_city', $delivery_address->city);
            $request->set('ship_to_phone_num', $delivery_address->phone_mobile ? $delivery_address->phone_mobile : $delivery_address->phone);
            $request->set('ship_to_country', $delivery_country->iso_code);
            if ($delivery_address->id_state) {
                $state = new State((int) $delivery_address->id_state);
                $request->set('ship_to_state', $state->iso_code);
            }
        }

        // Prepare cart data to send to gateway.
        if (Configuration::get('LYRA_COMMON_CATEGORY') !== 'CUSTOM_MAPPING') {
            $category = Configuration::get('LYRA_COMMON_CATEGORY');
        } else {
            $oney_categories = @unserialize(Configuration::get('LYRA_CATEGORY_MAPPING'));
        }

        $subtotal = 0;
        $products = $cart->getProducts(true);
        if (count($products) <= self::LYRA_CART_MAX_NB_PRODUCTS || $this->proposeOney($data)) {
            $product_label_regex_not_allowed = '#[^A-Z0-9ÁÀÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÇ ]#ui';

            foreach ($products as $product) {
                if (! isset($category)) {
                    // Build query to get product default category.
                    $sql = 'SELECT `id_category_default` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = '
                        . (int) $product['id_product'];
                    $db_category = Db::getInstance()->getValue($sql);

                    $category = $oney_categories[$db_category];
                }

                $price_in_cents = $currency->convertAmountToInteger($product['price']);
                $qty = (int) $product['cart_quantity'];

                if ((! $this->force_local_cart_data && (Configuration::get('LYRA_SEND_CART_DETAIL') === 'True')) || $this->needs_cart_data) {
                    $request->addProduct(
                        Tools::substr(preg_replace($product_label_regex_not_allowed, ' ', $product['name']), 0, 255),
                        $price_in_cents,
                        $qty,
                        $product['id_product'],
                        $category,
                        number_format($product['rate'], 4, '.', '')
                    );
                }

                $subtotal += $price_in_cents * $qty;
            }
        }

        // Set misc optional params as possible.
        $request->set(
            'shipping_amount',
            $currency->convertAmountToInteger($cart->getOrderTotal(false, Cart::ONLY_SHIPPING))
        );

        // Recalculate tax_amount to avoid rounding problems.
        $tax_amount_in_cents = $request->get('amount') - $subtotal - $request->get('shipping_amount');
        if ($tax_amount_in_cents < 0) {
            // When order is discounted.
            $tax_amount = $cart->getOrderTotal(true) - $cart->getOrderTotal(false);
            $tax_amount_in_cents = ($tax_amount <= 0) ? 0 : $currency->convertAmountToInteger($tax_amount);
        }

        $request->set('tax_amount', $tax_amount_in_cents);

        // VAT amount for colombian payment means.
        $request->set('totalamount_vat', $tax_amount_in_cents);

        if (Configuration::get('LYRA_SEND_SHIP_DATA') === 'True' || $this->proposeOney($data)) {
            // Set information about delivery mode.
            $this->setAdditionalData($cart, $delivery_address, $request, $this->proposeOney($data), $this->isOney34());
        }

        // Override capture delay if defined in submodule.
        if (is_numeric(Configuration::get($this->prefix . 'DELAY'))) {
            $request->set('capture_delay', Configuration::get($this->prefix . 'DELAY'));
        }

        // Override validation mode if defined in submodule.
        if (Configuration::get($this->prefix . 'VALIDATION') !== '-1') {
            $request->set('validation_mode', Configuration::get($this->prefix . 'VALIDATION'));
        }

        $request->set('order_info', 'module_id=' . $this->name);

        // Activate 3DS?
        $threeds_mpi = null;
        $threeds_min_amount_options = @unserialize(Configuration::get('LYRA_3DS_MIN_AMOUNT'));
        if (is_array($threeds_min_amount_options) && ! empty($threeds_min_amount_options)) {
            $customer_group = (int) Customer::getDefaultGroupId($cart->id_customer);

            $all_min_amount = $threeds_min_amount_options[0]['min_amount']; // Value configured for all groups.

            $min_amount = null;
            foreach ($threeds_min_amount_options as $key => $value) {
                if (empty($value) || $key === 0) {
                    continue;
                }

                if ($key === $customer_group) {
                    $min_amount = $value['min_amount'];
                    break;
                }
            }

            if (! $min_amount) {
                $min_amount = $all_min_amount;
            }

            if ($min_amount && ($amount < $min_amount)) {
                $threeds_mpi = '2';
            }
        }

        $request->set('threeds_mpi', $threeds_mpi);

        // Return URL.
        $request->set('url_return', Context::getContext()->link->getModuleLink('lyra', 'submit', array(), true));

        return $request;
    }

    private function setAdditionalData($cart, $delivery_address, &$lyra_request, $use_oney = false, $isOney34 = false)
    {
        // Oney delivery options defined in admin panel.
        $shipping_options = @unserialize(Configuration::get('LYRA_ONEY_SHIP_OPTIONS'));

        // Retrieve carrier ID from cart.
        if (isset($cart->id_carrier) && $cart->id_carrier > 0) {
            $carrier_id = $cart->id_carrier;
        } else {
            $delivery_option_list = $cart->getDeliveryOptionList();

            $delivery_option = $cart->getDeliveryOption();
            $carrier_key = $delivery_option[(int) $cart->id_address_delivery];
            $carrier_list = $delivery_option_list[(int) $cart->id_address_delivery][$carrier_key]['carrier_list'];

            foreach (array_keys($carrier_list) as $id) {
                $carrier_id = $id;
                break;
            }
        }

        $not_allowed_chars = "#[^A-Z0-9ÁÀÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÇ /'-]#ui";
        $address_not_allowed_chars = "#[^A-Z0-9ÁÀÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÇ/ '.,-]#ui";
        $relay_point_name = null;

        // Set shipping params.

        if ($cart->isVirtualCart() || ! isset($carrier_id) || ! is_array($shipping_options) || empty($shipping_options)) {
            // No shipping options or virtual cart.
            $lyra_request->set('ship_to_type', 'ETICKET');
            $lyra_request->set('ship_to_speed', 'EXPRESS');

            $lyra_request->set(
                'ship_to_delivery_company_name',
                preg_replace($not_allowed_chars, ' ', Configuration::get('PS_SHOP_NAME'))
            );
        } elseif (self::isSupportedRelayPoint($carrier_id)) {
            // Specific supported relay point carrier.
            $lyra_request->set('ship_to_type', 'RELAY_POINT');
            $lyra_request->set('ship_to_speed', 'STANDARD');

            $address = '';
            $city = '';
            $zipcode = '';
            $country = 'FR';

            switch (true) {
                case self::isTntRelayPoint($carrier_id):
                    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . "tnt_carrier_drop_off` WHERE `id_cart` = '" . (int) $cart->id . "'";
                    $row = Db::getInstance()->getRow($sql);

                    if (! $row) {
                        break;
                    }

                    $address = $isOney34 ? $row['address'] : $row['name'] . ' ' . $row['address']; // Relay point address.
                    $relay_point_name = $isOney34 ? $row['name'] : null; // Relay point name.
                    $city = $row['city'];
                    $zipcode = $row['zipcode'];

                    break;
                case self::isNewMondialRelay($carrier_id):
                    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'mondialrelay_selected_relay` s WHERE s.`id_cart` = ' . (int) $cart->id;
                    $row = Db::getInstance()->getRow($sql);

                    if (! $row) {
                        break;
                    }

                    // Relay point address.
                    $address =  $isOney34 ? $row['selected_relay_adr3'] : $row['selected_relay_adr1'] . ' ' . $row['selected_relay_adr3'];
                    $relay_point_name = $isOney34 ? $row['selected_relay_adr1'] : null; // Relay point name.
                    $city = $row['selected_relay_city'];
                    $zipcode = $row['selected_relay_postcode'];
                    $country = $row['selected_relay_country_iso'];
                    break;
                case self::isMondialRelay($carrier_id):
                    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'mr_selected` s WHERE s.`id_cart` = ' . (int) $cart->id;
                    $row = Db::getInstance()->getRow($sql);

                    if (! $row) {
                        break;
                    }

                    // Relay point address.
                    $address = $isOney34 ? $row['MR_Selected_LgAdr3'] : $row['MR_Selected_LgAdr1'] . ' ' . $row['MR_Selected_LgAdr3'];
                    $relay_point_name = $isOney34 ? $row['MR_Selected_LgAdr1'] : null; // Relay point name.
                    $city = $row['MR_Selected_Ville'];
                    $zipcode = $row['MR_Selected_CP'];
                    $country = $row['MR_Selected_Pays'];

                    break;
                case self::isDpdFranceRelais($carrier_id):
                    $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'dpdfrance_shipping` WHERE `id_cart` = ' . (int) $cart->id;
                    $row = Db::getInstance()->getRow($sql);

                    if (! $row) {
                        break;
                    }

                    $address = $row['address1'] . ' ' . $row['address2'];
                    $address = $isOney34 ? $address : $row['company'] . ' ' . $address; // Relay point address.
                    $relay_point_name = $isOney34 ? $row['company'] : null; // Relay point name.
                    $city = $row['city'];
                    $zipcode = $row['postcode'];

                    $ps_country = new Country((int) $row['id_country']);
                    $country = $ps_country->iso_code;

                    break;

                case (self::isColissimoRelay($carrier_id) && $delivery_address->company /* Relay point. */):
                    $address = $delivery_address->address1 . ' ' . $delivery_address->address2;
                    $address = $isOney34 ? $address : $delivery_address->company . ' ' . $address; // Relay point address.
                    $relay_point_name = $isOney34 ? $delivery_address->company: null; // Relay point name.

                    // Already set address.
                    $city = $lyra_request->get('ship_to_city');
                    $zipcode = $lyra_request->get('ship_to_zip');
                    $country = $lyra_request->get('ship_to_country');
                    break;

                // Can implement more specific relay point carriers logic here.
            }

            // Override shipping address.
            $lyra_request->set('ship_to_street', preg_replace($address_not_allowed_chars, ' ', $address));
            $lyra_request->set('ship_to_street2', null);
            $lyra_request->set('ship_to_zip', $zipcode);
            $lyra_request->set('ship_to_city', preg_replace($not_allowed_chars, ' ', $city));
            $lyra_request->set('ship_to_state', null);
            $lyra_request->set('ship_to_country', $country);

            $delivery_company = preg_replace($not_allowed_chars, ' ', $address . ' ' . $zipcode . ' ' . $city);
            $lyra_request->set('ship_to_delivery_company_name', $delivery_company);
        } else {
            // Other cases
            $delivery_type = isset($shipping_options[$carrier_id]) ? $shipping_options[$carrier_id]['type'] : 'PACKAGE_DELIVERY_COMPANY';
            $delivery_speed = isset($shipping_options[$carrier_id]) ? $shipping_options[$carrier_id]['speed'] : 'STANDARD';
            $lyra_request->set('ship_to_type', $delivery_type);
            $lyra_request->set('ship_to_speed', $delivery_speed);

            if (isset($shipping_options[$carrier_id])) {
                $company_name = $shipping_options[$carrier_id]['label'];
            } else {
                $delivery_option_list = $cart->getDeliveryOptionList();

                $delivery_option = $cart->getDeliveryOption();
                $carrier_key = $delivery_option[(int) $cart->id_address_delivery];
                $carrier_list = $delivery_option_list[(int) $cart->id_address_delivery][$carrier_key]['carrier_list'];
                $company_name = $carrier_list[$carrier_id]['instance']->name;
            }

            if ($delivery_type === 'RECLAIM_IN_SHOP') {
                $shop_name = preg_replace($not_allowed_chars, ' ', Configuration::get('PS_SHOP_NAME'));

                $lyra_request->set('ship_to_street', $shop_name . ' ' . $shipping_options[$carrier_id]['address']);
                $lyra_request->set('ship_to_street2', null);
                $lyra_request->set('ship_to_zip', $shipping_options[$carrier_id]['zip']);
                $lyra_request->set('ship_to_city', $shipping_options[$carrier_id]['city']);
                $lyra_request->set('ship_to_country', 'FR');

                $company_name = $shop_name . ' ' . $shipping_options[$carrier_id]['address'] . ' ' .
                    $shipping_options[$carrier_id]['zip'] . ' ' . $shipping_options[$carrier_id]['city'];
            }

            // Enable delay select for rows with speed equals PRIORITY.
            if ($shipping_options[$carrier_id]['speed'] === 'PRIORITY') {
                $lyra_request->set('ship_to_delay', $shipping_options[$carrier_id]['delay']);
            }

            $lyra_request->set('ship_to_delivery_company_name', $company_name);
        }

        if ($use_oney) {
            // Modify address to send it to Oney.

            if ($lyra_request->get('ship_to_street')) { // If there is a delivery address.
                $lyra_request->set('ship_to_status', 'PRIVATE'); // By default PrestaShop doesn't manage customer type.

                $address = $lyra_request->get('ship_to_street') . ' ' . $lyra_request->get('ship_to_street2');

                $lyra_request->set('ship_to_street', preg_replace($address_not_allowed_chars, ' ', $address));
                $lyra_request->set('ship_to_street2', $relay_point_name);

                // Send FR even address is in DOM-TOM unless form is rejected.
                $lyra_request->set('ship_to_country', 'FR');
            }

            // By default PrestaShop doesn't manage customer type.
            $lyra_request->set('cust_status', 'PRIVATE');

            // Send FR even address is in DOM-TOM unless form is rejected.
            $lyra_request->set('cust_country', 'FR');
        }
    }

    private static function isSupportedRelayPoint($carrier_id)
    {
        return self::isTntRelayPoint($carrier_id) || self::isNewMondialRelay($carrier_id)
            || self::isMondialRelay($carrier_id) || self::isDpdFranceRelais($carrier_id)
            || self::isColissimoRelay($carrier_id);
    }

    private static function isTntRelayPoint($carrier_id)
    {
        if (! Configuration::get('TNT_CARRIER_JD_ID')) {
            return false;
        }

        return (Configuration::get('TNT_CARRIER_JD_ID') == $carrier_id);
    }

    private static function isNewMondialRelay($carrier_id)
    {
        if (! Configuration::get('MONDIALRELAY_WEBSERVICE_ENSEIGNE')) {
            return false;
        }

        $sql = 'SELECT `id_mondialrelay_carrier_method` FROM `' . _DB_PREFIX_ . 'mondialrelay_carrier_method` WHERE `id_carrier` = ' . (int) $carrier_id;
        $id_method = Db::getInstance()->getValue($sql);

        return ! empty($id_method);
    }

    private static function isMondialRelay($carrier_id)
    {
        if (! Configuration::get('MONDIAL_RELAY')) {
            return false;
        }

        $sql = 'SELECT `id_mr_method` FROM `' . _DB_PREFIX_ . 'mr_method` WHERE `id_carrier` = ' . (int) $carrier_id;
        $id_method = Db::getInstance()->getValue($sql);

        return ! empty($id_method);
    }

    private static function isDpdFranceRelais($carrier_id)
    {
        if (! Configuration::get('DPDFRANCE_RELAIS_CARRIER_ID')) {
            return false;
        }

        return (Configuration::get('DPDFRANCE_RELAIS_CARRIER_ID') == $carrier_id);
    }

    private static function isColissimoRelay($carrier_id)
    {
        // SoColissimo not available.
        if (! Configuration::get('SOCOLISSIMO_CARRIER_ID')) {
            return false;
        }

        // SoColissimo is not selected as shipping method.
        return (Configuration::get('SOCOLISSIMO_CARRIER_ID') == $carrier_id);
    }

    /**
     * Shortcut for module translation function.
     *
     * @param string $text
     * @return localized text
     */
    protected function l($string)
    {
        /* @var Lyra */
        $lyra = Module::getInstanceByName('lyra');
        return $lyra->l($string, Tools::strtolower(get_class($this)));
    }
}
