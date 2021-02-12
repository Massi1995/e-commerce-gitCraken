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

require_once _PS_MODULE_DIR_ . 'lyra/classes/LyraApi.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/LyraResponse.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/LyraFileLogger.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/LyraWsException.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/LyraTools.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/LyraRest.php';

require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/AbstractLyraPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraAncvPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraChoozeoPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraFullcbPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraMultiPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraOneyPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraOney34Payment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraPaypalPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraSepaPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraSofortPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraStandardPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraOtherPayment.php';
require_once _PS_MODULE_DIR_ . 'lyra/classes/payment/LyraGroupedOtherPayment.php';

/**
 * Payment module main class.
 */
class Lyra extends PaymentModule
{
    // Regular expressions.
    const DELIVERY_COMPANY_ADDRESS_REGEX = '#^[A-Z0-9ÁÀÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÇ /\'-]{1,72}$#ui';
    const DELIVERY_COMPANY_LABEL_REGEX = '#^[A-Z0-9ÁÀÂÄÉÈÊËÍÌÎÏÓÒÔÖÚÙÛÜÇ /\'-]{1,55}$#ui';

    const PAYMENT_DETAILS_PS17 = true; // Display more payment details using a closed SAV thread on PS >= 1.7.1.2.
    private $logger;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->name = 'lyra';
        $this->tab = 'payments_gateways';
        $this->version = '1.13.7';
        $this->author = 'Lyra Network';
        $this->controllers = array('redirect', 'submit', 'rest', 'iframe');
        $this->module_key = '';
        $this->is_eu_compatible = 1;
        $this->need_instance = 0;

        $this->logger = LyraTools::getLogger();

        // Check version compatibility.
        $minor = Tools::substr(_PS_VERSION_, strrpos(_PS_VERSION_, '.') + 1);
        $replace = (int) $minor + 1;
        $start = Tools::strlen(_PS_VERSION_) - Tools::strlen($minor);
        $version = substr_replace(_PS_VERSION_, (string) $replace, $start);
        $this->ps_versions_compliancy = array('min' => '1.5.0.0', 'max' => $version);

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $order_id = (int) Tools::getValue('id_order', 0);
        $order = new Order($order_id);
        if (($order->module == $this->name) && ($this->context->controller instanceof OrderConfirmationController)) {
            // Patch to use different display name according to the used payment submodule.
            $this->displayName = $order->payment;
        } else {
            $this->displayName = 'Lyra Collect';
        }

        $this->description = $this->l('Accept payments by credit cards');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your module details?');
    }

    /**
     * @see PaymentModuleCore::install()
     */
    public function install()
    {
        if (version_compare(_PS_VERSION_, '1.5', '<')) {
            // Incompatible version of PrestaShop.
            return false;
        }

        // Install hooks.
        if (! parent::install() || ! $this->registerHook('header') || ! $this->registerHook('paymentReturn')
            || ! $this->registerHook('adminOrder') || ! $this->registerHook('actionObjectOrderSlipAddBefore')
            || ! $this->registerHook('actionProductCancel')
            || ! $this->registerHook('actionOrderStatusUpdate')
            || ! $this->registerHook('actionOrderStatusPostUpdate')
            || ! $this->registerHook('actionAdminCarrierWizardControllerSaveBefore')
            || ! $this->registerHook('actionAdminCarriersOptionsModifier')) {
            return false;
        }

        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            if (! $this->registerHook('payment') || ! $this->registerHook('displayPaymentEU')) {
                return false;
            }
        } else {
            if (! $this->registerHook('paymentOptions')) {
                return false;
            }
        }

        // Set default values.
        foreach (LyraTools::getAdminParameters() as $param) {
            if (in_array($param['key'], LyraTools::$multi_lang_fields)) {
                $default = LyraTools::convertIsoArrayToIdArray($param['default']);
            } elseif (is_array($param['default'])) {
                $default = serialize($param['default']);
            } else {
                $default = $param['default'];
            }

            if (! Configuration::updateValue($param['key'], $default, false, false, false)) {
                return false;
            }
        }

        // Create custom order states.
        if (LyraTools::$plugin_features['oney'] && ! Configuration::get('LYRA_OS_ONEY_PENDING')) {
            // Create Oney pending confirmation order state.
            $name = array(
                'en' => 'Funding request in progress',
                'fr' => 'Demande de financement en cours',
                'de' => 'Finanzierungsanfrage im Gange',
                'es' => 'Solicitud de financiación en curso'
            );

            $oney_state = new OrderState();
            $oney_state->name = LyraTools::convertIsoArrayToIdArray($name);
            $oney_state->invoice = false;
            $oney_state->send_email = false;
            $oney_state->module_name = $this->name;
            $oney_state->color = '#FF8C00';
            $oney_state->unremovable = true;
            $oney_state->hidden = false;
            $oney_state->logable = false;
            $oney_state->delivery = false;
            $oney_state->shipped = false;
            $oney_state->paid = false;

            if (! $oney_state->save() || ! Configuration::updateValue('LYRA_OS_ONEY_PENDING', $oney_state->id)) {
                return false;
            }

            // Add small icon to state.
            @copy(
                _PS_MODULE_DIR_ . 'lyra/views/img/os_oney.gif',
                _PS_IMG_DIR_ . 'os/' . Configuration::get('LYRA_OS_ONEY_PENDING') . '.gif'
            );
        }

        if (! Configuration::get('LYRA_OS_TO_VALIDATE')) {
            // Create to validate payment order state.
            $name = array(
                'en' => 'To validate payment',
                'fr' => 'Paiement à valider',
                'de' => 'Um zu überprüfen Zahlung',
                'es' => 'Para validar el pago'
            );

            $tvp_state = new OrderState();
            $tvp_state->name = LyraTools::convertIsoArrayToIdArray($name);
            $tvp_state->invoice = false;
            $tvp_state->send_email = false;
            $tvp_state->module_name = $this->name;
            $tvp_state->color = '#FF8C00';
            $tvp_state->unremovable = true;
            $tvp_state->hidden = false;
            $tvp_state->logable = false;
            $tvp_state->delivery = false;
            $tvp_state->shipped = false;
            $tvp_state->paid = false;

            if (! $tvp_state->save() || ! Configuration::updateValue('LYRA_OS_TO_VALIDATE', $tvp_state->id)) {
                return false;
            }

            // Add small icon to state.
            @copy(
                _PS_MODULE_DIR_ . 'lyra/views/img/os_tvp.gif',
                _PS_IMG_DIR_ . 'os/' . Configuration::get('LYRA_OS_TO_VALIDATE') . '.gif'
            );
        }

        if (! Configuration::get('PS_OS_OUTOFSTOCK_PAID') && ! Configuration::get('LYRA_OS_PAYMENT_OUTOFSTOCK')) {
            // Create a payment OK but order out of stock state.
            $name = array(
                'en' => 'On backorder (payment accepted)',
                'fr' => 'En attente de réapprovisionnement (paiement accepté)',
                'de' => 'Artikel nicht auf Lager (Zahlung eingegangen)',
                'es' => 'Pedido pendiente por falta de stock (pagado) '
            );

            $oos_state = new OrderState();
            $oos_state->name = LyraTools::convertIsoArrayToIdArray($name);
            $oos_state->invoice = true;
            $oos_state->send_email = true;
            $oos_state->module_name = $this->name;
            $oos_state->color = '#FF69B4';
            $oos_state->unremovable = true;
            $oos_state->hidden = false;
            $oos_state->logable = false;
            $oos_state->delivery = false;
            $oos_state->shipped = false;
            $oos_state->paid = true;
            $oos_state->template = 'outofstock';

            if (! $oos_state->save() || ! Configuration::updateValue('LYRA_OS_PAYMENT_OUTOFSTOCK', $oos_state->id)) {
                return false;
            }

            // Add small icon to state.
            @copy(
                _PS_MODULE_DIR_ . 'lyra/views/img/os_oos.gif',
                _PS_IMG_DIR_ . 'os/' . Configuration::get('LYRA_OS_PAYMENT_OUTOFSTOCK') . '.gif'
            );
        }

        if (! Configuration::get('LYRA_OS_AUTH_PENDING')) {
            // Create payment pending authorization order state.
            $name = array(
                'en' => 'Pending authorization',
                'fr' => 'En attente d\'autorisation',
                'de' => 'Autorisierung angefragt',
                'es' => 'En espera de autorización'
            );

            $auth_state = new OrderState();
            $auth_state->name = LyraTools::convertIsoArrayToIdArray($name);
            $auth_state->invoice = false;
            $auth_state->send_email = false;
            $auth_state->module_name = $this->name;
            $auth_state->color = '#FF8C00';
            $auth_state->unremovable = true;
            $auth_state->hidden = false;
            $auth_state->logable = false;
            $auth_state->delivery = false;
            $auth_state->shipped = false;
            $auth_state->paid = false;

            if (! $auth_state->save() || ! Configuration::updateValue('LYRA_OS_AUTH_PENDING', $auth_state->id)) {
                return false;
            }

            // Add small icon to state.
            @copy(
                _PS_MODULE_DIR_ . 'lyra/views/img/os_auth.gif',
                _PS_IMG_DIR_ . 'os/' . Configuration::get('LYRA_OS_AUTH_PENDING') . '.gif'
            );
        }

        if ((LyraTools::$plugin_features['sofort'] || LyraTools::$plugin_features['sepa'])
            && ! Configuration::get('LYRA_OS_TRANS_PENDING')) {
            // Create SOFORT and SEPA pending funds order state.
            $name = array(
                'en' => 'Pending funds transfer',
                'fr' => 'En attente du transfert de fonds',
                'de' => 'Warten auf Geldtransfer',
                'es' => 'En espera de la transferencia de fondos'
            );

            $sofort_state = new OrderState();
            $sofort_state->name = LyraTools::convertIsoArrayToIdArray($name);
            $sofort_state->invoice = false;
            $sofort_state->send_email = false;
            $sofort_state->module_name = $this->name;
            $sofort_state->color = '#FF8C00';
            $sofort_state->unremovable = true;
            $sofort_state->hidden = false;
            $sofort_state->logable = false;
            $sofort_state->delivery = false;
            $sofort_state->shipped = false;
            $sofort_state->paid = false;

            if (! $sofort_state->save() || ! Configuration::updateValue('LYRA_OS_TRANS_PENDING', $sofort_state->id)) {
                return false;
            }

            // Add small icon to state.
            @copy(
                _PS_MODULE_DIR_ . 'lyra/views/img/os_trans.gif',
                _PS_IMG_DIR_ . 'os/' . Configuration::get('LYRA_OS_TRANS_PENDING') . '.gif'
            );
        }

        // Clear module compiled templates.
        $tpls = array(
            'redirect', 'redirect_bc', 'redirect_js',
            'iframe/redirect', 'iframe/redirect_bc', 'iframe/response', 'iframe/loader',

            'bc/payment_ancv', 'bc/payment_choozeo', 'bc/payment_fullcb', 'bc/payment_multi', 'bc/payment_oney',
            'bc/payment_oney34','bc/payment_paypal', 'bc/payment_sepa', 'bc/payment_sofort', 'bc/payment_std_eu',
            'bc/payment_std_iframe', 'bc/payment_std', 'bc/payment_std_rest',

            'payment_choozeo', 'payment_fullcb', 'payment_multi', 'payment_oney', 'payment_oney34',
            'payment_return', 'payment_std_iframe', 'payment_std', 'payment_std_rest'
        );
        foreach ($tpls as $tpl) {
            $this->context->smarty->clearCompiledTemplate($this->getTemplatePath($tpl . '.tpl'));
        }

        return true;
    }

    /**
     * @see PaymentModuleCore::uninstall()
     */
    public function uninstall()
    {
        $result = true;
        foreach (LyraTools::getAdminParameters() as $param) {
            $result &= Configuration::deleteByName($param['key']);
        }

        // Delete all obsolete gateway params but not custom order states.
        $result &= Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . "configuration` WHERE `name` LIKE 'LYRA_%' AND `name` NOT LIKE 'LYRA_OS_%'"
        );

        return $result && parent::uninstall();
    }

    /**
     * Admin form management.
     * @return string
     */
    public function getContent()
    {
        $msg = '';

        if (Tools::isSubmit('lyra_submit_admin_form')) {
            $this->postProcess();

            if (empty($this->_errors)) {
                // No error, display update ok message.
                $msg .= $this->displayConfirmation($this->l('Settings updated.'));
            } else {
                // Display errors.
                $msg .= $this->displayError(implode('<br />', $this->_errors));
            }

            $msg .= '<br />';
        }

        return $msg . $this->renderForm();
    }

    /**
     * Validate and save module admin parameters.
     */
    private function postProcess()
    {
        require_once _PS_MODULE_DIR_ . 'lyra/classes/LyraRequest.php';
        $request = new LyraRequest(); // New instance of LyraRequest for parameters validation.

        // Load and validate from request.
        foreach (LyraTools::getAdminParameters() as $param) {
            $key = $param['key']; // PrestaShop parameter key.

            if (! Tools::getIsset($key)) {
                // If field is disabled, don't save it.
                continue;
            }

            $label = $this->l($param['label'], 'back_office'); // Translated human-readable label.
            $name = isset($param['name']) ? $param['name'] : null; // Gateway API parameter name.

            $value = Tools::getValue($key, null);
            if ($value === '') { // Consider empty strings as null.
                $value = null;
            }

            // Load countries restriction list.
            $isCountriesList = (Tools::substr($key, -12) === '_COUNTRY_LST');

            if (in_array($key, LyraTools::$multi_lang_fields)) {
                if (! is_array($value) || empty($value)) {
                    $value = array();
                }
            } elseif (in_array($key, LyraTools::$group_amount_fields)) {
                if (! is_array($value) || empty($value)) {
                    $value = array();
                } else {
                    $error = false;
                    foreach ($value as $id => $option) {
                        if (($key === 'LYRA_CHOOZEO_OPTIONS') && ! isset($option['enabled'])) {
                            $value[$id]['enabled'] = 'False';
                        }

                        if (isset($option['min_amount']) && $option['min_amount'] && (! is_numeric($option['min_amount']) || $option['min_amount'] < 0)) {
                            $value[$id]['min_amount'] = ''; // Error, reset incorrect value.
                            $error = true;
                        }

                        if (isset($option['max_amount']) && $option['max_amount'] && (! is_numeric($option['max_amount']) || $option['max_amount'] < 0)) {
                            $value[$id]['max_amount'] = ''; // Error, reset incorrect value.
                            $error = true;
                        }
                    }

                    if ($error) {
                        $this->_errors[] = sprintf($this->l('One or more values are invalid for field « %s ». Only valid entries are saved.'), $label);
                    }
                }

                $value = serialize($value);
            } elseif ($key === 'LYRA_MULTI_OPTIONS') {
                if (! is_array($value) || empty($value)) {
                    $value = array();
                } else {
                    $error = false;
                    foreach ($value as $id => $option) {
                        if (! is_numeric($option['count'])
                                || ! is_numeric($option['period'])
                                || ($option['first'] && (! is_numeric($option['first']) || $option['first'] < 0 || $option['first'] > 100))) {
                            unset($value[$id]); // Error, do not save this option.
                            $error = true;
                        } else {
                            $default = is_string($option['label']) && $option['label'] ?
                                $option['label'] : $option['count'] . ' x';
                            $option_label = is_array($option['label']) ? $option['label'] : array();

                            foreach (Language::getLanguages(false) as $language) {
                                $lang = $language['id_lang'];
                                if (! isset($option_label[$lang]) || empty($option_label[$lang])) {
                                    $option_label[$lang] = $default;
                                }
                            }

                            $value[$id]['label'] = $option_label;
                        }
                    }

                    if ($error) {
                        $this->_errors[] = sprintf($this->l('One or more values are invalid for field « %s ». Only valid entries are saved.'), $label);
                    }
                }

                $value = serialize($value);
            } elseif ($key === 'LYRA_AVAILABLE_LANGUAGES') {
                $value = (is_array($value) && ! empty($value)) ? implode(';', $value) : '';
            } elseif ($key === 'LYRA_STD_PAYMENT_CARDS' || $key === 'LYRA_MULTI_PAYMENT_CARDS') {
                if (! is_array($value) || in_array('', $value)) {
                    $value = array();
                }

                $value = implode(';', $value);
                if (Tools::strlen($value) > 127) {
                    $this->_errors[] = $this->l('Too many card types are selected.');
                    continue;
                }

                $name = 'payment_cards';
            } elseif ($isCountriesList) {
                if (! is_array($value) || in_array('', $value)) {
                    $value = array();
                }

                $value = implode(';', $value);
            } elseif ($key === 'LYRA_ONEY_SHIP_OPTIONS') {
                if (! is_array($value) || empty($value)) {
                    $value = array();
                } else {
                    foreach ($value as $id => $option) {
                        if (! preg_match(self::DELIVERY_COMPANY_LABEL_REGEX, $option['label'])) {
                            unset($value[$id]); // Error, not save this option.

                            $this->_errors[] = sprintf($this->l('The field « %1$s » is invalid: please check column « %2$s » of the option « %3$s » in section « %4$s ».'), $label, $this->l('Name'), $id, $this->l('ADDITIONAL OPTIONS'))
                                . ' ' . sprintf($this->l('Use %1$d alphanumeric characters, accentuated characters and these special characters: space, slash, hyphen, apostrophe.'), 55);
                        }

                        if ($option['type'] === 'RECLAIM_IN_SHOP') {
                            $address = ($option['address'] ? ' ' . $option['address'] : '') . ($option['zip'] ? ' ' . $option['zip'] : '')
                                . ($option['city'] ? ' ' . $option['city'] : '');
                            if (! preg_match(self::DELIVERY_COMPANY_ADDRESS_REGEX, $address)) {
                                unset($value[$id]); // Error, not save this option.

                                $this->_errors[] = sprintf($this->l('The field « %1$s » is invalid: please check column « %2$s » of the option « %3$s » in section « %4$s ».'), $label, $this->l('Address'), $id, $this->l('ADDITIONAL OPTIONS'))
                                    . ' ' . sprintf($this->l('Use %1$d alphanumeric characters, accentuated characters and these special characters: space, slash, hyphen, apostrophe.'), 65);
                            }
                        }
                    }
                }

                $value = serialize($value);
            } elseif ($key === 'LYRA_CATEGORY_MAPPING') {
                if (Tools::getValue('LYRA_COMMON_CATEGORY', null) !== 'CUSTOM_MAPPING') {
                    continue;
                }

                if (! is_array($value) || empty($value)) {
                    $value = array();
                }

                $value = serialize($value);
            } elseif (($key === 'LYRA_ONEY_ENABLED') && ($value === 'True')) {
                $error = $this->validateOney();

                if (is_string($error) && ! empty($error)) {
                    $this->_errors[] = $error;
                    $value = 'False'; // There is errors, not allow FacilyPay Oney activation.
                }
            } elseif (($key === 'LYRA_ONEY34_ENABLED') && ($value === 'True')) {
                $error = $this->validateOney(false, true);

                if (is_string($error) && ! empty($error)) {
                    $this->_errors[] = $error;
                    $value = 'False'; // There is errors, not allow 3 or 4 times Oney activation.
                }
            } elseif (in_array($key, LyraTools::$amount_fields)) {
                if (! empty($value) && (! is_numeric($value) || $value < 0)) {
                    $this->_errors[] = sprintf($this->l('Invalid value « %1$s » for field « %2$s ».'), $value, $label);
                    continue;
                }
            } elseif (($key === 'LYRA_STD_PROPOSE_ONEY') && ($value === 'True')) {
                $oney_enabled = Tools::getValue('LYRA_ONEY_ENABLED', 'False') === 'True' ? true : false;

                if ($oney_enabled) {
                    $value = 'False';
                    $this->_errors[] = $this->l('FacilyPay Oney payment mean cannot be enabled in standard payment and in FacilyPay Oney sub-module.');
                    $this->_errors[] = $this->l('You must disable the FacilyPay Oney sub-module to enable it in standard payment.');
                } else {
                    $error = $this->validateOney(true, false);

                    if (is_string($error) && ! empty($error)) {
                        $this->_errors[] = $error;
                        $value = 'False'; // There is errors, not allow Oney activation in standard payment.
                    }
                }
            } elseif (($key === 'LYRA_ONEY_OPTIONS') && (Tools::getValue('LYRA_ONEY_ENABLE_OPTIONS', 'False') === 'True')) {
                if (! is_array($value) || empty($value)) {
                    $value = array();
                } else {
                    $error = false;
                    foreach ($value as $id => $option) {
                        if (! is_numeric($option['count']) || ! is_numeric($option['rate']) || empty($option['code'])) {
                            unset($value[$id]); // Error, do not save this option.
                            $error = true;
                        } else {
                            $default = is_string($option['label']) && $option['label'] ?
                                $option['label'] : $option['count'] . ' x';
                            $option_label = is_array($option['label']) ? $option['label'] : array();

                            foreach (Language::getLanguages(false) as $language) {
                                $lang = $language['id_lang'];
                                if (! isset($option_label[$lang]) || empty($option_label[$lang])) {
                                    $option_label[$lang] = $default;
                                }
                            }

                            $value[$id]['label'] = $option_label;
                        }
                    }

                    if ($error) {
                        $this->_errors[] = sprintf($this->l('One or more values are invalid for field « %s ». Only valid entries are saved.'), $label);
                    }
                }

                $value = serialize($value);
            } elseif ($key === 'LYRA_ONEY34_OPTIONS') {
                if (! is_array($value) || empty($value)) {
                    $value = array();
                } else {
                    $error = false;
                    foreach ($value as $id => $option) {
                        if (! is_numeric($option['count']) || ! is_numeric($option['rate']) || empty($option['code'])) {
                            unset($value[$id]); // Error, do not save this option.
                            $error = true;
                        } else {
                            $default = is_string($option['label']) && $option['label'] ?
                            $option['label'] : $option['count'] . ' x';
                            $option_label = is_array($option['label']) ? $option['label'] : array();

                            foreach (Language::getLanguages(false) as $language) {
                                $lang = $language['id_lang'];
                                if (! isset($option_label[$lang]) || empty($option_label[$lang])) {
                                    $option_label[$lang] = $default;
                                }
                            }

                            $value[$id]['label'] = $option_label;
                        }
                    }

                    if ($error) {
                        $this->_errors[] = sprintf($this->l('One or more values are invalid for field « %s ». Only valid entries are saved.'), $label);
                    }
                }

                $value = serialize($value);
            } elseif (($key === 'LYRA_FULLCB_OPTIONS') && (Tools::getValue('LYRA_FULLCB_ENABLE_OPTS', 'False') === 'True')) {
                if (! is_array($value) || empty($value)) {
                    $value = array();
                } else {
                    $error = false;
                    foreach ($value as $id => $option) {
                        if (! isset($option['enabled'])) {
                            $value[$id]['enabled'] = 'False';
                        }

                        if ($option['min_amount'] && ! is_numeric($option['min_amount']) || $option['min_amount'] < 0) {
                            $value[$id]['min_amount'] = ''; // Error, reset incorrect value.
                            $error = true;
                        }

                        if ($option['max_amount'] && ! is_numeric($option['max_amount']) || $option['max_amount'] < 0) {
                            $value[$id]['max_amount'] = ''; // Error, reset incorrect value.
                            $error = true;
                        }

                        if (! is_numeric($option['rate']) || $option['rate'] < 0 || $option['rate'] > 100) {
                            $value[$id]['rate'] = '0'; // Error, reset incorrect value.
                            $error = true;
                        }

                        if (! is_numeric($option['cap']) || $option['cap'] < 0) {
                            $value[$id]['cap'] = ''; // Error, reset incorrect value.
                            $error = true;
                        }
                    }

                    if ($error) {
                        $this->_errors[] = sprintf($this->l('One or more values are invalid for field « %s ». Only valid entries are saved.'), $label);
                    }
                }

                $value = serialize($value);
            } elseif ($key === 'LYRA_OTHER_PAYMENT_MEANS') {
                if (! is_array($value) || empty($value)) {
                    $value = array();
                } else {
                    $error = false;
                    $used_cards = array();
                    $titles = array(
                        'fr' => 'Paiement avec %s',
                        'en' => 'Payment with %s',
                        'de' => 'Zahlung mit %s',
                        'es' => 'Pago con %s'
                    );

                    $cards = LyraApi::getSupportedCardTypes();

                    foreach ($value as $id => $option) {
                        if (in_array($option['code'], $used_cards)) {
                            unset($value[$id]);
                            continue;
                        } else {
                            $used_cards[] = $option['code'];
                        }

                        if (($option['min_amount'] && ! is_numeric($option['min_amount']))
                            || $option['min_amount'] < 0
                            || ($option['max_amount'] && ! is_numeric($option['max_amount']))
                            || $option['max_amount'] < 0
                            || ($option['min_amount'] && ($option['max_amount'] && $option['min_amount'] > $option['max_amount']))
                            || ($option['capture'] && ! is_numeric($option['capture']))) {
                            unset($value[$id]); // Error, do not save this option.
                            $error = true;
                        } else {
                            $selected_card = $cards[$option['code']];
                            $option_title = is_array($option['title']) ? $option['title'] : array();

                            foreach (Language::getLanguages(false) as $language) {
                                $lang = $language['id_lang'];
                                $iso = $language['iso_code'];
                                $default = isset($titles[$iso]) ? $titles[$iso] : $titles['en'];

                                if (! isset($option_title[$lang]) || empty($option_title[$lang])) {
                                    $option_title[$lang] = is_string($option['title']) && $option['title'] ?
                                        $option['title'] : sprintf($default, $selected_card);
                                }
                            }

                            $value[$id]['title'] = $option_title;
                        }
                    }

                    if ($error) {
                        $this->_errors[] = sprintf($this->l('One or more values are invalid for field « %s ». Only valid entries are saved.'), $label);
                    }
                }

                $value = serialize($value);
            } elseif ($key === 'LYRA_STD_REST_PLACEHLDR') {
                $value = serialize($value);
            } elseif ($key === 'LYRA_STD_REST_ATTEMPTS') {
                if ($value && (! is_numeric($value) || $value < 0 || $value > 10)) {
                    $this->_errors[] = sprintf($this->l('Invalid value « %1$s » for field « %2$s ».'), $value, $label);
                    continue;
                }
            }

            // Validate with LyraRequest.
            if ($name && ($name !== 'theme_config')) {
                $values = is_array($value) ? $value : array($value); // To check multilingual fields.
                $error = false;

                foreach ($values as $v) {
                    if (! $request->set($name, $v)) {
                        $error = true;
                        if (empty($v)) {
                            $this->_errors[] = sprintf($this->l('The field « %s » is mandatory.'), $label);
                        } else {
                            $this->_errors[] = sprintf($this->l('Invalid value « %1$s » for field « %2$s ».'), $v, $label);
                        }
                    }
                }

                if ($error) {
                    continue; // Do not save fields with errors.
                }
            }

            // Valid field: try to save into DB.
            if (! Configuration::updateValue($key, $value)) {
                $this->_errors[] = sprintf($this->l('Problem occurred while saving field « %s ».'), $label);
            } else {
                // Temporary variable set to update PrestaShop cache.
                Configuration::set($key, $value);
            }
        }
    }

    private function validateOney($inside = false, $isOney34 = false)
    {
        $label = $isOney34 ? $this->l('Payment in 3 or 4 times Oney', 'lyraoney34payment') : $this->l('Payment with FacilyPay Oney', 'lyraoneypayment');

        if (Configuration::get('PS_ALLOW_MULTISHIPPING')) {
            return sprintf($this->l('Multishipping is activated. %s cannot be used.'), $label);
        }

        if (! $inside) {
            $key = $isOney34 ? 'LYRA_ONEY34_AMOUNTS' : 'LYRA_ONEY_AMOUNTS' ;
            $group_amounts = Tools::getValue($key);

            $default_min = $group_amounts[0]['min_amount'];
            $default_max = $group_amounts[0]['max_amount'];

            if (empty($default_min) || empty($default_max)) {
                return sprintf($this->l('Please, enter minimum and maximum amounts in %s tab as agreed with Banque Accord.'), $label);
            }

            $label = sprintf($this->l('%s - Customer group amount restriction'), $label);

            foreach ($group_amounts as $id => $group) {
                if (empty($group) || $id === 0) { // All groups.
                    continue;
                }

                $min_amount = $group['min_amount'];
                $max_amount = $group['max_amount'];
                if (($min_amount && $min_amount < $default_min) || ($max_amount && $max_amount > $default_max)) {
                    return sprintf($this->l('One or more values are invalid for field « %s ». Only valid entries are saved.'), $label);
                }
            }

            if ($isOney34 && ! Tools::getValue('LYRA_ONEY34_OPTIONS')) {
                return sprintf($this->l('The field « %s » is mandatory.'), $this->l('Payment in 3 or 4 times Oney - Payment options', 'back_office'));
            }
        }

        return true;
    }

    private function renderForm()
    {
        $this->addJS('lyra.js');
        $this->context->controller->addJqueryUI('ui.accordion');

        $html = '';

        if (version_compare(_PS_VERSION_, '1.6', '>=')) {
            $html .= '<style type="text/css">
                            #content {
                                min-width: inherit !important;
                            }
                     </style>';
            $html .= "\n";
        }

        require_once _PS_MODULE_DIR_ . 'lyra/classes/admin/LyraHelperForm.php';

        $this->context->smarty->assign(LyraHelperForm::getAdminFormContext());
        $form = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'lyra/views/templates/admin/back_office.tpl');

        $prefered_post_vars = 0;
        $prefered_post_vars += substr_count($form, 'name="LYRA_');
        $prefered_post_vars += 100; // To take account of dynamically created inputs.

        if ((ini_get('suhosin.post.max_vars') && ini_get('suhosin.post.max_vars') < $prefered_post_vars)
                || (ini_get('suhosin.request.max_vars') && ini_get('suhosin.request.max_vars') < $prefered_post_vars)) {
            $html .= $this->displayError(sprintf($this->l('Warning, please increase the suhosin patch for PHP post and request limits to save module configurations correctly. Recommended value is %s.'), $prefered_post_vars));
        } elseif (ini_get('max_input_vars') && ini_get('max_input_vars') < $prefered_post_vars) {
            $html .= $this->displayError(sprintf($this->l('Warning, please increase the value of the max_input_vars directive in php.ini to to save module configurations correctly. Recommended value is %s.'), $prefered_post_vars));
        }

        $html .= $form;
        return $html;
    }

    /**
     * Payment method selection page header.
     *
     * @param array $params
     * @return string|void
     */
    public function hookHeader($params)
    {
        $controller = $this->context->controller;
        if ($controller instanceof OrderController || $controller instanceof OrderOpcController) {
            if (isset($this->context->cookie->lyraPayErrors)) {
                // Process errors from other pages.
                $controller->errors = array_merge(
                    $controller->errors,
                    explode("\n", $this->context->cookie->lyraPayErrors)
                );
                unset($this->context->cookie->lyraPayErrors);

                // Unset HTTP_REFERER from global server variable to avoid back link display in error message.
                $_SERVER['HTTP_REFERER'] = null;
                $this->context->smarty->assign('server', $_SERVER);
            }

            // Add main module CSS.
            $this->addCss('lyra.css');

            $html = '';

            $standard = new LyraStandardPayment();
            if ($standard->isAvailable($this->context->cart)) {
                if ($standard->getEntryMode() === '5') {
                    $test_mode = Configuration::get('LYRA_MODE') === 'TEST';
                    $pub_key = $test_mode ? Configuration::get('LYRA_PUBKEY_TEST') :
                        Configuration::get('LYRA_PUBKEY_PROD');

                    // URL where to redirect after payment.
                    $return_url = $this->context->link->getModuleLink('lyra', 'rest', array(), true);

                    // Current language or default if not supported.
                    $language = Language::getLanguage((int) $this->context->cart->id_lang);
                    $language_iso_code = Tools::strtolower($language['iso_code']);
                    if (! LyraApi::isSupportedLanguage($language_iso_code)) {
                        $language_iso_code = Configuration::get('LYRA_DEFAULT_LANGUAGE');
                    }

                    $html .= '<script>
                                var LYRA_LANGUAGE = "' . $language_iso_code . '";
                              </script>';

                    $html .= '<script src="' . LyraTools::getDefault('STATIC_URL') . 'js/krypton-client/V4.0/stable/kr-payment-form.min.js"
                                      kr-public-key="' . $pub_key . '"
                                      kr-post-url-success="' . $return_url . '"
                                      kr-post-url-refused="' . $return_url . '"
                                      kr-language="' . $language_iso_code . '"';

                    $rest_placeholders = @unserialize(Configuration::get('LYRA_STD_REST_PLACEHLDR'));
                    if ($pan_label = $rest_placeholders['pan'][$language['id_lang']]) {
                        $html .= ' kr-placeholder-pan="' . $pan_label . '"';
                    }

                    if ($expiry_label = $rest_placeholders['expiry'][$language['id_lang']]) {
                        $html .= ' kr-placeholder-expiry="' . $expiry_label . '"';
                    }

                    if ($cvv_label = $rest_placeholders['cvv'][$language['id_lang']]) {
                        $html .= ' kr-placeholder-security-code="' . $cvv_label . '"';
                    }

                    $html .= '></script>' . "\n";

                    // Theme and plugins, should be loaded after the javascript library.
                    $rest_theme = Configuration::get('LYRA_STD_REST_THEME') ? Configuration::get('LYRA_STD_REST_THEME') : 'material';
                    $html .= '<link rel="stylesheet" href="' . LyraTools::getDefault('STATIC_URL') . 'js/krypton-client/V4.0/ext/' . $rest_theme . '-reset.css">
                              <script src="' . LyraTools::getDefault('STATIC_URL') . 'js/krypton-client/V4.0/ext/' . $rest_theme . '.js"></script>';

                    $this->context->smarty->assign('lyra_rest_theme', $rest_theme);

                    $page = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';

                    Media::addJsDef(array('lyra' => array('restUrl' => $return_url, 'pageType' => $page)));
                    $this->addJS('rest.js');
                }
            }

            // Add backward compatibility module CSS.
            if (version_compare(_PS_VERSION_, '1.7', '<')) {
                $this->addCss('lyra_bc.css');

                // Load payment module style to apply it to our tag.
                if ($this->useMobileTheme()) {
                    $css_file = _PS_THEME_MOBILE_DIR_ . 'css/global.css';
                } else {
                    $css_file = _PS_THEME_DIR_ . 'css/global.css';
                }

                $css = Tools::file_get_contents(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $css_file));

                $matches = array();
                $res = preg_match_all('#(p\.payment_module(?:| a| a\:hover) ?\{[^\}]+\})#i', $css, $matches);
                if ($res && ! empty($matches) && isset($matches[1]) && is_array($matches[1]) && ! empty($matches[1])) {
                    $html .= '<style type="text/css">'."\n";
                    $html .= str_ireplace('p.payment_module', 'div.payment_module', implode("\n", $matches[1]))."\n";
                    $html .= '</style>'."\n";
                }
            }

            return $html;
        }
    }

    protected function useMobileTheme()
    {
        if (method_exists(get_parent_class($this), 'useMobileTheme')) {
            return parent::useMobileTheme();
        } elseif (method_exists($this->context, 'getMobileDevice')) {
            return ($this->context->getMobileDevice() && file_exists(_PS_THEME_MOBILE_DIR_ . 'layout.tpl'));
        }

        return false;
    }

    private function addJs($js_file)
    {
        $controller = $this->context->controller;

        if (method_exists($controller, 'registerJavascript')) { // PrestaShop 1.7.
            $controller->registerJavascript(
                'module-lyra',
                'modules/' . $this->name . '/views/js/' . $js_file,
                array('position' => 'bottom', 'priority' => 150)
            );
        } else {
            $controller->addJs($this->_path . 'views/js/' . $js_file);
        }
    }

    private function addCss($css_file)
    {
        $controller = $this->context->controller;

        if (method_exists($controller, 'registerStylesheet')) { // PrestaShop 1.7.
            $controller->registerStylesheet(
                'module-lyra-' . basename($css_file, '.png'),
                'modules/' . $this->name . '/views/css/' . $css_file,
                array('media' => 'all', 'priority' => 90)
            );
        } else {
            $controller->addCss($this->_path . 'views/css/' . $css_file, 'all');
        }
    }

    /**
     * Payment function, payment button render if Advanced EU Compliance module is used.
     *
     * @param array $params
     * @return void|array
     */
    public function hookDisplayPaymentEU($params)
    {
        if (! $this->active) {
            return;
        }

        if (! $this->checkCurrency()) {
            return;
        }

        $cart = $this->context->cart;

        $standard = new LyraStandardPayment();
        if ($standard->isAvailable($cart)) {
            $payment_options = array(
                'cta_text' => $standard->getTitle((int) $cart->id_lang),
                'logo' => $this->_path . 'views/img/' . $standard->getLogo(),
                'form' => $this->display(__FILE__, 'bc/payment_std_eu.tpl')
            );

            return $payment_options;
        }
    }

    /**
     * Payment function, display payment buttons/forms for all submodules.
     *
     * @param array $params
     * @return void|string
     */
    public function hookPayment($params)
    {
        if (! $this->active) {
            return;
        }

        // Currency support.
        if (! $this->checkCurrency()) {
            return;
        }

        $cart = $this->context->cart;

        // Version tag for specific styles.
        $tag = version_compare(_PS_VERSION_, '1.6', '<') ? 'lyra15' : 'lyra16';
        $this->context->smarty->assign('lyra_tag', $tag);

        $html = '';

        $standard = new LyraStandardPayment();
        if ($standard->isAvailable($cart)) {
            $this->context->smarty->assign($standard->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $standard->getTplName());
        }

        $multi = new LyraMultiPayment();
        if ($multi->isAvailable($cart)) {
            $this->context->smarty->assign($multi->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $multi->getTplName());
        }

        $choozeo = new LyraChoozeoPayment();
        if ($choozeo->isAvailable($cart)) {
            $this->context->smarty->assign($choozeo->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $choozeo->getTplName());
        }

        $oney = new LyraOneyPayment();
        if ($oney->isAvailable($cart)) {
            $this->context->smarty->assign($oney->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $oney->getTplName());
        }

        $oney34 = new LyraOney34Payment();
        if ($oney34->isAvailable($cart)) {
            $this->context->smarty->assign($oney34->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $oney34->getTplName());
        }

        $fullcb = new LyraFullcbPayment();
        if ($fullcb->isAvailable($cart)) {
            $this->context->smarty->assign($fullcb->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $fullcb->getTplName());
        }

        $ancv = new LyraAncvPayment();
        if ($ancv->isAvailable($cart)) {
            $this->context->smarty->assign($ancv->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $ancv->getTplName());
        }

        $sepa = new LyraSepaPayment();
        if ($sepa->isAvailable($cart)) {
            $this->context->smarty->assign($sepa->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $sepa->getTplName());
        }

        $paypal = new LyraPaypalPayment();
        if ($paypal->isAvailable($cart)) {
            $this->context->smarty->assign($paypal->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $paypal->getTplName());
        }

        $sofort = new LyraSofortPayment();
        if ($sofort->isAvailable($cart)) {
            $this->context->smarty->assign($sofort->getTplVars($cart));
            $html .= $this->display(__FILE__, 'bc/' . $sofort->getTplName());
        }

        $other_payments = LyraOtherPayment::getAvailablePaymentMeans($cart);
        if (Configuration::get('LYRA_OTHER_GROUPED_VIEW') === 'True' && count($other_payments) > 1) {
            $grouped = new LyraGroupedOtherPayment();
            $grouped->setPaymentMeans($other_payments);

            if ($grouped->isAvailable($cart)) {
                $this->context->smarty->assign($grouped->getTplVars($cart));
                $html .= $this->display(__FILE__, 'bc/' . $grouped->getTplName());
            }
        } else {
            foreach ($other_payments as $option) {
                $other = new LyraOtherPayment();
                $other->init($option['code'], $option['title'], $option['min_amount'], $option['max_amount']);

                if ($other->isAvailable($cart)) {
                    $this->context->smarty->assign($other->getTplVars($cart));
                    $html .= $this->display(__FILE__, 'bc/' . $other->getTplName());
                }
            }
        }

        return $html;
    }

    /**
     * Payment function, display payment buttons/forms for all submodules in PrestaShop 1.7+.
     *
     * @param array $params
     * @return void|array[\PrestaShop\PrestaShop\Core\Payment\PaymentOption]
     */
    public function hookPaymentOptions($params)
    {
        if (! $this->active) {
            return array();
        }

        if (! $this->checkCurrency()) {
            return array();
        }

        $cart = $this->context->cart;

        /**
         * @var array[\PrestaShop\PrestaShop\Core\Payment\PaymentOption]
         */
        $options = array();

        // Version tag for specific styles.
        $this->context->smarty->assign('lyra_tag', 'lyra17');

        /**
         * AbstractLyraPayment::getPaymentOption() returns a payment option of type
         * \PrestaShop\PrestaShop\Core\Payment\PaymentOption
         */

        $standard = new LyraStandardPayment();
        if ($standard->isAvailable($cart)) {
            $option = $standard->getPaymentOption($cart);

            // Payment by identifier.
            $customersConfig = @unserialize(Configuration::get('LYRA_CUSTOMERS_CONFIG'));
            $savedIdentifier = isset($customersConfig[$cart->id_customer]['standard']['n']) ? $customersConfig[$cart->id_customer]['standard']['n'] : '';

            $additionalForm = '';
            $oneClickPayment = (Configuration::get('LYRA_STD_1_CLICK_PAYMENT') === 'True' && $savedIdentifier) ;
            if ($oneClickPayment) {
                $this->context->smarty->assign($standard->getTplVars($cart));
                $additionalForm = $this->fetch('module:lyra/views/templates/hook/payment_std_oneclick.tpl');
                $option->setAdditionalInformation($additionalForm);
            }

            if ($standard->hasForm() || $oneClickPayment) {
                if (! $oneClickPayment) {
                    $this->context->smarty->assign($standard->getTplVars($cart));
                }

                $form = $this->fetch('module:lyra/views/templates/hook/' . $standard->getTplName());
                $isRestPayment = strpos($standard->getTplName(), 'rest'); // Check if it's really a payment by embedded fields.

                if ($standard->getEntryMode() === '4' ||
                    (($standard->getEntryMode() === '5') && $isRestPayment)) {
                    // IFrame or REST mode.
                    $option->setAdditionalInformation($form . $additionalForm);
                    if ($oneClickPayment) {
                        $option->setForm('<form id="lyra_standard" onsubmit="javascript: lyraSubmit(event);"><input id="lyra_payment_by_identifier" type="hidden" name="lyra_payment_by_identifier" value="1" /></form>');
                    } else {
                        $option->setForm('<form id="lyra_standard" onsubmit="javascript: lyraSubmit(event);"></form>');
                    }
                } else {
                    $option->setForm($form);
                }
            }

            $options[] = $option;
        }

        $multi = new LyraMultiPayment();
        if ($multi->isAvailable($cart)) {
            $option = $multi->getPaymentOption($cart);

            if ($multi->hasForm()) {
                $this->context->smarty->assign($multi->getTplVars($cart));
                $form = $this->fetch('module:lyra/views/templates/hook/' . $multi->getTplName());
                $option->setForm($form);
            }

            $options[] = $option;
        }

        $choozeo = new LyraChoozeoPayment();
        if ($choozeo->isAvailable($cart)) {
            $option = $choozeo->getPaymentOption($cart);

            if ($choozeo->hasForm()) {
                $this->context->smarty->assign($choozeo->getTplVars($cart));
                $form = $this->fetch('module:lyra/views/templates/hook/' . $choozeo->getTplName());
                $option->setForm($form);
            }

            $options[] = $option;
        }

        $oney = new LyraOneyPayment();
        if ($oney->isAvailable($cart)) {
            $option = $oney->getPaymentOption($cart);

            if ($oney->hasForm()) {
                $this->context->smarty->assign($oney->getTplVars($cart));
                $form = $this->fetch('module:lyra/views/templates/hook/' . $oney->getTplName());
                $option->setForm($form);
            }

            $options[] = $option;
        }

        $oney34 = new LyraOney34Payment();
        if ($oney34->isAvailable($cart)) {
            $option = $oney34->getPaymentOption($cart);

            if ($oney34->hasForm()) {
                $this->context->smarty->assign($oney34->getTplVars($cart));
                $form = $this->fetch('module:lyra/views/templates/hook/' . $oney34->getTplName());
                $option->setForm($form);
            }

            $options[] = $option;
        }

        $fullcb = new LyraFullcbPayment();
        if ($fullcb->isAvailable($cart)) {
            $option = $fullcb->getPaymentOption($cart);

            if ($fullcb->hasForm()) {
                $this->context->smarty->assign($fullcb->getTplVars($cart));
                $form = $this->fetch('module:lyra/views/templates/hook/' . $fullcb->getTplName());
                $option->setForm($form);
            }

            $options[] = $option;
        }

        $ancv = new LyraAncvPayment();
        if ($ancv->isAvailable($cart)) {
            $options[] = $ancv->getPaymentOption($cart);
        }

        $sepa = new LyraSepaPayment();
        if ($sepa->isAvailable($cart)) {
            $options[] = $sepa->getPaymentOption($cart);
        }

        $paypal = new LyraPaypalPayment();
        if ($paypal->isAvailable($cart)) {
            $options[] = $paypal->getPaymentOption($cart);
        }

        $sofort = new LyraSofortPayment();
        if ($sofort->isAvailable($cart)) {
            $options[] = $sofort->getPaymentOption($cart);
        }

        $other_payments = LyraOtherPayment::getAvailablePaymentMeans($cart);
        if (Configuration::get('LYRA_OTHER_GROUPED_VIEW') === 'True' && count($other_payments) > 1) {
            $grouped = new LyraGroupedOtherPayment();
            $grouped->setPaymentMeans($other_payments);

            if ($grouped->isAvailable($cart)) {
                $option = $grouped->getPaymentOption($cart);

                if ($grouped->hasForm()) {
                    $this->context->smarty->assign($grouped->getTplVars($cart));
                    $form = $this->fetch('module:lyra/views/templates/hook/' . $grouped->getTplName());
                    $option->setForm($form);
                }

                $options[] = $option;
            }
        } else {
            foreach ($other_payments as $option) {
                $other = new LyraOtherPayment();
                $other->init($option['code'], $option['title'], $option['min_amount'], $option['max_amount']);

                if ($other->isAvailable($cart)) {
                    $options[] = $other->getPaymentOption($cart);
                }
            }
        }

        return $options;
    }

    private function checkCurrency()
    {
        $cart = $this->context->cart;

        $cart_currency = new Currency((int) $cart->id_currency);
        $currencies = $this->getCurrency((int) $cart->id_currency);

        if (! is_array($currencies) || empty($currencies)) {
            return false;
        }

        foreach ($currencies as $currency) {
            if ($cart_currency->id == $currency['id_currency']) {
                // Cart currency is allowed for this module.
                return LyraApi::findCurrencyByAlphaCode($cart_currency->iso_code) != null;
            }
        }

        return false;
    }

    /**
     * Manage payement gateway response.
     *
     * @param array $params
     */
    public function hookPaymentReturn($params)
    {
        $order = isset($params['order']) ? $params['order'] : $params['objOrder'];

        if (! $this->active || ($order->module != $this->name)) {
            return;
        }

        $error = (Tools::getValue('error') === 'yes');
        $amount_error = (Tools::getValue('amount_error') === 'yes');

        $array = array(
            'check_url_warn' => (Tools::getValue('check_url_warn') === 'yes'),
            'maintenance_mode' => ! Configuration::get('PS_SHOP_ENABLE'),
            'prod_info' => (Tools::getValue('prod_info') === 'yes'),
            'error_msg' => $error,
            'amount_error_msg' => $amount_error
        );

        if (! $error) {
            $array['total_to_pay'] = Tools::displayPrice(
                number_format($order->total_paid_real, 2),
                new Currency($order->id_currency),
                false
            );

            $array['id_order'] = $order->id;
            $array['status'] = 'ok';
            $array['shop_name'] = Configuration::get('PS_SHOP_NAME');

            if (isset($order->reference) && ! empty($order->reference)) {
                $array['reference'] = $order->reference;
            }
        }

        $this->context->smarty->assign($array);

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    /**
     * Before order details display in backend.
     *
     * @param array $params
     */
    public function hookAdminOrder($params)
    {
        if (isset($this->context->cookie->lyraRefundWarn)) {
            $this->context->controller->warnings[] = $this->context->cookie->lyraRefundWarn;
            unset($this->context->cookie->lyraRefundWarn);
        }
    }

    /**
     *  Before updating order status.
     *
     * @param array $params
     */
    public function hookActionOrderStatusUpdate($params)
    {
        $order = new Order((int) $params['id_order']);
        if (! $this->active || ($order->module != $this->name)) {
            return;
        }

        if ($params['newOrderStatus']->id !== (int) Configuration::get('PS_OS_REFUND')) {
            return;
        }

        if ($order->total_paid_real <= 0) {
            // Order already cancelled or refunded.
            $this->logger->logInfo("Order #{$order->id} was already cancelled or refunded.");
            return;
        }

        // Update order status is manually changed to "Refunded" (not by refund function).
        $this->context->cookie->lyraManualUpdateRefundStatus = 'True';

        // If any error during WS refund redirect to order details to avoid display success message.
        if (! $this->refund($order, $order->total_paid_real)) {
            Tools::redirectAdmin(AdminController::$currentIndex . '&id_order=' . $order->id . '&vieworder&token=' . Tools::getValue('token'));
        }

        return true;
    }

    /**
     *  After updating order status.
     *
     * @param array $params
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $order = new Order((int) $params['id_order']);
        if (! $this->active || ($order->module != $this->name)) {
            return;
        }

        // If it is manual update of order state to refunded, update order total paid real to 0.
        if (isset($this->context->cookie->lyraManualUpdateRefundStatus)
            && ($this->context->cookie->lyraManualUpdateRefundStatus === 'True')) {
            unset($this->context->cookie->lyraManualUpdateRefundStatus);
            $order->total_paid_real = 0;
            $order->update();
        }

        return true;
    }

    /**
     * Before order slip add in backend.
     *
     * @param OrderSlip $orderSlip
     */
    public function hookActionObjectOrderSlipAddBefore($orderSlip)
    {
        $orderSlipObject = $orderSlip['object'];
        $order_id = (int) $orderSlipObject->id_order;
        $order = new Order($order_id);

        if (! $this->active || ($order->module != $this->name)) {
            return;
        }

        // Stop the refund if the merchant want to generate a discount.
        if (Tools::isSubmit('generateDiscount')) {
            return;
        }

        // Get amount from OrderSlip, for now it's a workaround instead of use OrderSlip->amount for a bug in prestashop calculation.
        $amount = ! Tools::getValue('TaxMethod') ? $orderSlipObject->total_products_tax_excl : $orderSlipObject->total_products_tax_incl;

        // Add shipping cost amount.
        $amount += $orderSlipObject->shipping_cost_amount;

        // If any error during WS refund redirect to order details to avoid creation of a credit and displaying success message.
        if (! $this->refund($order, $amount)) {
            // No refund, so get back refunded products quantities, and available products stock quantities.
            $id_order_details = Tools::isSubmit('generateCreditSlip') ? Tools::getValue('cancelQuantity')
                : Tools::getValue('partialRefundProductQuantity');
            foreach ($id_order_details as $id_order_detail => $quantity ) {
                // Update order detail.
                $order_detail = new OrderDetail($id_order_detail);
                $order_detail->product_quantity_refunded -= $quantity;
                $order_detail->update();

                // Update product available quantity.
                StockAvailable::updateQuantity($order_detail->product_id, $order_detail->product_attribute_id, -$quantity, $order->id_shop);
            }

            Tools::redirectAdmin(AdminController::$currentIndex . '&id_order=' . $order_id . '&vieworder&token=' . Tools::getValue('token'));
        }

        return true;
    }

    /**
     * Refund money.
     *
     * @param Order $order
     * @param float $amount
     */
    private function refund($order, $amount)
    {
        // Client has not configured private key in module backend, let PrestaShop do offline refund.
        if (! $this->getPrivateKey()) {
            $this->logger->logWarning("Impossible to make online refund for order #{$order->id}: private key is not configured." .
                ' Let PrestaShop do offline refund.');
            // Allow offline refund and display warning message.
            $this->context->cookie->lyraRefundWarn = $this->l('Payment is refunded only in PrestaShop. Please, consider making necessary changes in Lyra Expert Back Office.');
            return true;
        }

        // Get currency.
        $orderCurrency = new Currency((int) $order->id_currency);
        $currency = LyraApi::findCurrencyByAlphaCode($orderCurrency->iso_code);
        $amount = Tools::ps_round($amount, $currency->getDecimals());

        $this->logger->logInfo("Start refund of {$amount} {$orderCurrency->sign} for order " .
            "#{$order->id} with Lyra Collect payment method.");

        $successStatuses = array_merge(
            LyraApi::getSuccessStatuses(),
            LyraApi::getPendingStatuses()
        );

        try {
            // Get payment details.
            $getPaymentDetails = $this->getPaymentDetails($order);
            $transStatus = $getPaymentDetails['detailedStatus'];

            if (! in_array($transStatus, $successStatuses)) {
                $msg = sprintf($this->l('Unexpected transaction status (%1$s).'), $transStatus);
                throw new Exception($msg);
            }

            $uuid = $getPaymentDetails['uuid'];
            $amountInCents = $currency->convertAmountToInteger($amount);

            $commentText = $this->getUserInfo();

            /** @var LyraRest $client */
            $client = new LyraRest(
                LyraTools::getDefault('REST_URL'),
                Configuration::get('LYRA_SITE_ID'),
                $this->getPrivateKey()
            );

            if ($transStatus === 'CAPTURED') { // Transaction captured, we can do refund.
                // No refund for captured transactions if order currency different than transaction currency.
                if ($orderCurrency->iso_code !== $getPaymentDetails['currency']) {
                    $msg = $this->l('Refund is not supported on captured transactions with currency conversion.');
                    throw new Exception($msg);
                }

                // Get already refunded amount.
                $refundedAmount = $getPaymentDetails['transactionDetails']['cardDetails']['captureResponse']['refundAmount'];

                if (empty($refundedAmount)) {
                    $refundedAmount = 0;
                }

                $remainingAmount = $getPaymentDetails['amount'] - $refundedAmount; // Calculate remaing amount.

                if ($remainingAmount < $amountInCents) {
                    $remainingAmountFloat = $currency->convertAmountToFloat($remainingAmount);
                    $msg = sprintf(
                        $this->l('Remaining amount (%1$s %2$s) is less than requested refund amount (%3$s %2$s).'),
                        $remainingAmountFloat,
                        $orderCurrency->sign,
                        $amount
                    );
                    throw new Exception($msg);
                }

                $requestData = array(
                    'uuid' => $uuid,
                    'amount' => $amountInCents,
                    'resolutionMode' => 'REFUND_ONLY',
                    'comment' => $commentText
                );

                $refundPaymentResponse = $client->post('V4/Transaction/CancelOrRefund', json_encode($requestData));

                LyraTools::checkRestResult(
                    $refundPaymentResponse,
                    $successStatuses
                );

                // Check operation type.
                $transType = $refundPaymentResponse['answer']['operationType'];

                if ($transType !== 'CREDIT') {
                    throw new Exception(sprintf($this->l('Unexpected transaction type received (%1$s).'), $transType));
                }

                $responseData = LyraTools::convertRestResult($refundPaymentResponse['answer'], true);
                $response = new LyraResponse($responseData, null, null, null);

                // Save refund transaction in PrestaShop.
                $refundedAmount += $amountInCents;

                // Create payment
                $this->createMessage($order, $response);
                $this->savePayment($order, $response);

                // Update order status if it is not a call from hookActionOrderStatusUpdate to avoid double status update.
                if (($refundedAmount == $getPaymentDetails['amount'])
                    && (! isset($this->context->cookie->lyraManualUpdateRefundStatus)
                        || ($this->context->cookie->lyraManualUpdateRefundStatus !== 'True'))) {
                    $order->setCurrentState((int) Configuration::get('PS_OS_REFUND'));
                }

                $this->logger->logInfo("Online money refund for order #{$order->id} is successful.");
            } else {
                $transAmount = $getPaymentDetails['amount'];

                // If order currency different than transaction currency we use transaction effective amount.
                if ($orderCurrency->iso_code != $getPaymentDetails['currency']) {
                    $transAmount = $getPaymentDetails['transactionDetails']['effectiveAmount'];
                }

                if ($amountInCents > $transAmount) {
                    $transAmountFloat = $currency->convertAmountToFloat($transAmount);
                    $msg = sprintf($this->l('Transaction amount (%1$s %2$s) is less than requested refund amount (%3$s %2$s).'), $transAmountFloat, $orderCurrency->sign, $amount);
                    throw new Exception($msg);
                }

                if ($amountInCents == $transAmount) { // Transaction cancel in gateway.
                    $requestData = array(
                        'uuid' => $uuid,
                        'resolutionMode' => 'CANCELLATION_ONLY',
                        'comment' => $commentText
                    );

                    $cancelPaymentResponse = $client->post('V4/Transaction/CancelOrRefund', json_encode($requestData));
                    LyraTools::checkRestResult($cancelPaymentResponse, array('CANCELLED'));

                    // Total refund, update order status as well.
                    $responseData = LyraTools::convertRestResult($cancelPaymentResponse['answer'], true);
                    $response = new LyraResponse($responseData, null, null, null);

                    // Save refund transaction in PrestaShop.
                    $this->savePayment($order, $response, true);

                    if (! isset($this->context->cookie->lyraManualUpdateRefundStatus)
                        || ($this->context->cookie->lyraManualUpdateRefundStatus !== 'True')) {
                        $order->setCurrentState((int) Configuration::get('PS_OS_REFUND'));
                    }

                    $this->logger->logInfo("Online payment cancel for order #{$order->id} is successful.");
                } else {
                    // Partial transaction cancel, call update WS.
                    $new_transaction_amount = $transAmount - $amountInCents;
                    $requestData = array(
                        'uuid' => $uuid,
                        'cardUpdate' => array(
                            'amount' => $new_transaction_amount,
                            'currency' => $currency->getAlpha3()
                        ),
                        'comment' => $commentText
                    );

                    $updatePaymentResponse = $client->post('V4/Transaction/Update', json_encode($requestData));

                    LyraTools::checkRestResult(
                        $updatePaymentResponse,
                        array(
                            'AUTHORISED',
                            'AUTHORISED_TO_VALIDATE',
                            'WAITING_AUTHORISATION',
                            'WAITING_AUTHORISATION_TO_VALIDATE'
                        )
                    );

                    $responseData = LyraTools::convertRestResult($updatePaymentResponse['answer'], true);
                    $response = new LyraResponse($responseData, null, null, null);

                    // Save refund transaction in PrestaShop.
                    $this->createMessage($order, $response);
                    $this->savePayment($order, $response);

                    $this->logger->logInfo("Online payment update for order #{$order->id} is successful.");
                }
            }

            return true;
        } catch (Exception $e) {
            $this->logger->logError("{$e->getMessage()}" . ($e->getCode() > 0 ? ' (' . $e->getCode() . ').' : ''));

            if ($e->getCode() === 'PSP_100') {
                // Merchant don't have offer allowing REST WS.
                // Allow offline refund and display warning message.
                $this->context->cookie->lyraRefundWarn = $this->l('Payment is refunded only in PrestaShop. Please, consider making necessary changes in Lyra Expert Back Office.');
                return true;
            }

            if ($e->getCode() <= -1) { // Manage cUrl errors.
                $message = sprintf($this->l('Error occurred when refunding payment for order #%1$s. Please consult the payment module log for more details.'), $order->reference);
            } elseif (! $e->getCode()) {
                $message = sprintf($this->l('Cannot refund payment for order #%1$s.'), $order->reference) . ' ' . $e->getMessage();
            } else {
                $message = $this->l('Refund error') . ': ' . $e->getMessage();
            }

            $this->context->cookie->lyraRefundWarn = $message;
            if (isset($this->context->cookie->lyraManualUpdateRefundStatus)
                && ($this->context->cookie->lyraManualUpdateRefundStatus === 'True')) {
                unset($this->context->cookie->lyraManualUpdateRefundStatus);
            }

            return false;
        }
    }

    /**
     * Get payment details for Order $order.
     *
     * @param Order $order
     * @return array
     */
    private function getPaymentDetails($order)
    {
        /** @var LyraRest $client */
        $client = new LyraRest(
            LyraTools::getDefault('REST_URL'),
            Configuration::get('LYRA_SITE_ID'),
            $this->getPrivateKey()
        );

        $requestData = array(
            'orderId' => $order->id_cart,
            'operationType' => 'DEBIT'
        );

        $getOrderResponse = $client->post('V4/Order/Get', json_encode($requestData));
        LyraTools::checkRestResult($getOrderResponse);

        // Order transactions organized by sequence numbers.
        $transBySequence = array();
        foreach ($getOrderResponse['answer']['transactions'] as $transaction) {
            $sequenceNumber = $transaction['transactionDetails']['sequenceNumber'];
            $transBySequence[$sequenceNumber] = $transaction;
        }

        // Get transaction Id from PrestaShop.
        $orderPaymentArray = OrderPayment::getByOrderReference($order->reference);
        $transactions = array();

        foreach ($orderPaymentArray as $orderPayment) {
            if ($orderPayment->amount <= 0) { // Do not treat REFUND transactions.
                continue;
            }

            $transactionId = $orderPayment->transaction_id;

            // Get the correct sequence number.
            $sequenceNumber = $transactionId ? strstr($transactionId, '-', true) : 1;
            $transactions[$sequenceNumber] = $transBySequence[$sequenceNumber];
        }

        // For the moment, return only the first transaction.
        // TODO return the whole array when refunding multiple payment.
        return reset($transactions);
    }

    private function getPrivateKey()
    {
        $test_mode = Configuration::get('LYRA_MODE') === 'TEST';
        $private_key = $test_mode ? Configuration::get('LYRA_PRIVKEY_TEST') : Configuration::get('LYRA_PRIVKEY_PROD');

        return $private_key;
    }

    private function getUserInfo()
    {
        $commentText = 'PrestaShop user: ' . $this->context->employee->email;
        $commentText .= ' ; IP address: ' . Tools::getRemoteAddr();

        return $commentText;
    }

    /**
     * Before (modifying or new) carrier save in backend.
     *
     * @param array $params
     */
    public function hookActionAdminCarrierWizardControllerSaveBefore($params)
    {
        if ((Configuration::get('LYRA_SEND_SHIP_DATA') === 'True') || (Configuration::get('LYRA_ONEY_ENABLED') === 'True') || (Configuration::get('LYRA_ONEY34_ENABLED') === 'True')) {
            $msg = $this->l('Warning! Do not forget to configure the shipping options mapping in the payment module: GENERAL CONFIGURATION > ADDITIONAL OPTIONS.');
            $this->context->cookie->lyraShippingOptionsWarn = $msg;
        }
    }

    /**
     * After (modifying or new) carrier save in backend.
     *
     * @param array $params
     */
    public function hookActionAdminCarriersOptionsModifier($params)
    {
        if (isset($this->context->cookie->lyraShippingOptionsWarn)) {
            $this->context->controller->warnings[] = $this->context->cookie->lyraShippingOptionsWarn;
            unset($this->context->cookie->lyraShippingOptionsWarn);
        }
    }

    /**
     * Save order and transaction info.
     *
     * @param Cart $cart
     * @param int $state
     * @param LyraResponse $response
     * @return Order
     */
    public function saveOrder($cart, $state, $response)
    {
        $this->logger->logInfo("Create order for cart #{$cart->id}.");

        // Retrieve customer from cart.
        $customer = new Customer((int) $cart->id_customer);

        $currency = LyraApi::findCurrency($response->get('currency'));
        $decimals = $currency->getDecimals();

        // PrestaShop id_currency from currency iso num code.
        $currency_id = Currency::getIdByIsoCode($currency->getAlpha3());

        // Real paid total on gateway.
        $paid_total = $currency->convertAmountToFloat($response->get('amount'));
        if (number_format($cart->getOrderTotal(), $decimals) == number_format($paid_total, $decimals)) {
            // To avoid rounding issues and bypass PaymentModule::validateOrder() check.
            $paid_total = $cart->getOrderTotal();
        }

        // Parse order_info parameter.
        $parts = explode('&', $response->get('order_info'));
        $module_id = Tools::substr($parts[0], Tools::strlen('module_id='));

        // Recover used payment method.
        $class_name = 'Lyra' . LyraTools::ucClassName($module_id) . 'Payment';
        if (! $module_id || ! class_exists($class_name)) {
            $this->logger->logWarning("Invalid submodule identifier ($module_id) received from gateway for cart #{$cart->id}.");

            // Use standard submodule as default.
            $class_name = 'LyraStandardPayment';
        }

        $payment = new $class_name();

        // Specific case of "Other payment means" submodule.
        if (is_a($payment, 'LyraOtherPayment')) {
            $method = LyraOtherPayment::getMethodByCode($response->get('card_brand'));
            $payment->init($method['code'], $method['title']);
        }

        $title = $payment->getTitle((int) $cart->id_lang);

        if (isset($parts[1])) {
            // This is multiple payment submodule.
            $option_id = Tools::substr($parts[1], Tools::strlen('option_id='));

            $multi_options = $payment::getAvailableOptions();
            $option = $multi_options[$option_id];
            $title .= $option ? ' (' . $option['count'] . ' x)' : '';
        }

        $this->logger->logInfo("Call PaymentModule::validateOrder() PrestaShop function to create order for cart #{$cart->id}.");

        // Call payment module validateOrder.
        $this->validateOrder(
            $cart->id,
            $state,
            $paid_total,
            $title, // Title defined in admin panel.
            null, // $message.
            array(), // $extraVars.
            $currency_id, // $currency_special.
            true, // $dont_touch_amount.
            $customer->secure_key
        );

        $this->logger->logInfo("PaymentModule::validateOrder() PrestaShop function called successfully for cart #{$cart->id}.");

        // Reload order.
        $order = new Order((int) Order::getOrderByCartId($cart->id));
        $this->logger->logInfo("Order #{$order->id} created successfully for cart #{$cart->id}.");

        $this->createMessage($order, $response);
        $this->savePayment($order, $response);
        $this->saveIdentifier($customer, $response);

        return $order;
    }


    /**
     * Update current order state.
     *
     * @param Order $order
     * @param int $order_state
     * @param LyraResponse $response
     */
    public function setOrderState($order, $order_state, $response)
    {
        $this->logger->logInfo(
            "Payment status for cart #{$order->id_cart} has changed. New order state is $order_state."
        );
        $order->setCurrentState($order_state);
        $this->logger->logInfo("Order state successfully changed, cart #{$order->id_cart}.");

        $this->createMessage($order, $response);
        $this->savePayment($order, $response);
    }

    /**
     * Create private message to information about order payment.
     *
     * @param Order $order
     * @param LyraResponse $response
     */
    public function createMessage($order, $response)
    {
        $msg_brand_choice = '';
        if ($response->get('brand_management')) {
            $brand_info = Tools::jsonDecode($response->get('brand_management'));
            $msg_brand_choice .= "\n";

            if (isset($brand_info->userChoice) && $brand_info->userChoice) {
                $msg_brand_choice .= $this->l('Card brand chosen by buyer.');
            } else {
                $msg_brand_choice .= $this->l('Default card brand used.');
            }
        }

        // 3DS extra message.
        $msg_3ds = "\n" . $this->l('3DS authentication : ');
        if ($status = $response->get('threeds_status')) {
            $msg_3ds .= $this->getThreedsStatus($status);
            $msg_3ds .= ($threeds_cavv = $response->get('threeds_cavv')) ? "\n" . $this->l('3DS certificate : ') . $threeds_cavv : '';
            $msg_3ds .= ($threeds_auth_type = $response->get('threeds_auth_type')) ? "\n" . $this->l('Authentication type : ') . $threeds_auth_type : '';
        } else {
            $msg_3ds .= 'UNAVAILABLE';
        }

        // IPN call source.
        $msg_src = ($url_check_src = $response->get('url_check_src')) ? "\n" . $this->l('IPN source : ') . $url_check_src : "";

        // Transaction UUID.
        $msg_trans_uuid = "\n" . $this->l('Transaction UUID : ') . $response->get('trans_uuid');

        $message = $response->getCompleteMessage() . $msg_brand_choice . $msg_3ds . $msg_src . $msg_trans_uuid;

        if (self::PAYMENT_DETAILS_PS17 && version_compare(_PS_VERSION_, '1.7.1.2', '>=')) {
            $msg = new CustomerMessage();
            $msg->message = $message;
            $msg->id_customer_thread = $this->createCustomerThread((int) $order->id);
            $msg->id_order = (int) $order->id;
            $msg->private = 1;
            $msg->read = 1;
            $msg->save();
        }

        // Create order message anyway to prevent changes on PrestaShop coming versions.
        $msg = new Message();
        $msg->message = $message;
        $msg->id_order = (int) $order->id;
        $msg->private = 1;
        $msg->add();

        // Mark message as read to archive it.
        Message::markAsReaded($msg->id, 0);
    }

    private function getThreedsStatus($status)
    {
        switch ($status) {
            case 'Y':
                return 'SUCCESS';

            case 'N':
                return 'FAILED';

            case 'U':
                return 'UNAVAILABLE';

            case 'A':
                return 'ATTEMPT';

            default :
                return $status;
        }
    }

    private function createCustomerThread($id_order)
    {
        $customerThread = new CustomerThread();
        $customerThread->id_shop = $this->context->shop->id;
        $customerThread->id_lang = $this->context->language->id;
        $customerThread->id_contact = 0;
        $customerThread->id_order = $id_order;
        $customerThread->id_customer = $this->context->customer->id;
        $customerThread->status = 'closed';
        $customerThread->email = $this->context->customer->email;
        $customerThread->token = Tools::passwdGen(12);
        $customerThread->add();

        return (int) $customerThread->id;
    }

    /**
     * Save payment information.
     *
     * @param Order $order
     * @param LyraResponse $response
     */
    public function savePayment($order, $response, $force_stop_payment_creation = false)
    {
        $payments = $order->getOrderPayments();

        $currency = LyraApi::findCurrency($response->get('currency'));
        $decimals = $currency->getDecimals();

        // Delete payments created by default and cancelled payments.
        if (is_array($payments) && ! empty($payments)) {
            $number = $response->get('sequence_number') ? $response->get('sequence_number') : '1';
            $trans_id = $number . '-' . $response->get('trans_id');
            $cancelled = $response->getTransStatus() === 'CANCELLED';

            $update = false;

            foreach ($payments as $payment) {
                if (! $payment->transaction_id || (($payment->transaction_id == $trans_id) && $cancelled)) {
                    // Round to avoid floats like 2.4868995751604E-14.
                    $order->total_paid_real = Tools::ps_round($order->total_paid_real - $payment->amount, $decimals);

                    // Delete payment and invoice reference.
                    $this->deleteOrderPayment($payment);

                    $update = true;
                }
            }

            if ($update) {
                if ($order->total_paid_real < 0) {
                    $order->total_paid_real = 0;
                }

                $order->update();
            }
        }

        if ((! $this->isSuccessState($order) && ! $response->isAcceptedPayment()) || $force_stop_payment_creation) {
            // No payment creation.
            return;
        }

        // Save transaction info.
        $this->logger->logInfo("Save payment information for cart #{$order->id_cart}.");

        $invoices = $order->getInvoicesCollection();
        $invoice = ($invoices && $invoices->getFirst()) ? $invoices->getFirst() : null;

        $payment_ids = array();

        // Parse order_info parameter.
        $parts = explode('&', $response->get('order_info'));

        // Recover option_id if any.
        $option_id ='';
        if (isset($parts[1])) {
            $option_id = Tools::substr($parts[1], Tools::strlen('option_id='));
        }

        if ($response->get('card_brand') === 'MULTI') {
            $sequences = Tools::jsonDecode($response->get('payment_seq'));
            $transactions = array_filter($sequences->transactions, 'Lyra::filterTransactions');

            $last_trs = end($transactions); // Last transaction.
            foreach ($transactions as $trs) {
                // Real paid total on gateway.
                $amount = $currency->convertAmountToFloat($trs->{'amount'});

                if ($trs === $last_trs) {
                    $remaining = $order->total_paid - $order->total_paid_real;
                    if (number_format($remaining, $decimals) == number_format($amount, $decimals)) {
                        // To avoid rounding problems and pass PaymentModule::validateOrder() check.
                        $amount = $remaining;
                    }
                }

                $trans_id = $trs->{'sequence_number'} . '-' . $trs->{'trans_id'};
                $timestamp = isset($trs->{'presentation_date'}) ? strtotime($trs->{'presentation_date'} . ' UTC') : time();

                $data = array(
                    'card_number' => $trs->{'card_number'},
                    'card_brand' => $trs->{'card_brand'},
                    'expiry_month' => isset($trs->{'expiry_month'}) ? $trs->{'expiry_month'} : null,
                    'expiry_year' => isset($trs->{'expiry_year'}) ? $trs->{'expiry_year'} : null
                );

                if (! ($pccId = $this->addOrderPayment($order, $invoice, $trans_id, $amount, $timestamp, $data))) {
                    return;
                }

                $payment_ids[] = $pccId;
            }
        } elseif ($option_id && (strpos($response->get('payment_config'), 'MULTI') !== false)) {
            $multi_options = LyraMultiPayment::getAvailableOptions();
            $option = $multi_options[$option_id];

            $count = (int) $option['count'];

            $total_amount = $response->get('amount');

            if (isset($option['first']) && $option['first']) {
                $first_amount = round($total_amount * $option['first'] / 100);
            } else {
                $first_amount = round($total_amount / $count);
            }

            $installment_amount = (int)(string)(($total_amount - $first_amount) / ($count - 1));

            $first_timestamp = strtotime($response->get('presentation_date').' UTC');

            $data = array(
                'card_number' => $response->get('card_number'),
                'card_brand' => $response->get('card_brand'),
                'expiry_month' => $response->get('expiry_month'),
                'expiry_year' => $response->get('expiry_year')
            );

            $total_paid_real = 0;
            for ($i = 1; $i <= $option['count']; $i++) {
                $trans_id = $i . '-' . $response->get('trans_id');

                $delay = (int) $option['period'] * ($i - 1);
                $timestamp = strtotime("+$delay days", $first_timestamp);

                switch (true) {
                    case ($i == 1): // First transaction.
                        $amount = $currency->convertAmountToFloat($first_amount);
                        break;
                    case ($i == $option['count']): // Last transaction.
                        $amount = $currency->convertAmountToFloat($total_amount) - $total_paid_real;

                        $remaining = $order->total_paid - $order->total_paid_real;
                        if (number_format($remaining, $decimals) == number_format($amount, $decimals)) {
                            // To avoid rounding problems and pass PaymentModule::validateOrder() check.
                            $amount = $remaining;
                        }

                        break;
                    default: // Others.
                        $amount = $currency->convertAmountToFloat($installment_amount);
                        break;
                }

                $total_paid_real += $amount;

                if (! ($pccId = $this->addOrderPayment($order, $invoice, $trans_id, $amount, $timestamp, $data))) {
                    return;
                }

                $payment_ids[] = $pccId;
            }
        } else {
            // Real paid total on gateway.
            $amount_in_cents = $response->get('amount');
            if ($response->get('effective_currency') && ($response->get('effective_currency') == $response->get('currency'))) {
                $amount_in_cents = $response->get('effective_amount'); // Use effective amount to get modified amount.
            }

            $amount = $currency->convertAmountToFloat($amount_in_cents);

            if (number_format($order->total_paid, $decimals) == number_format($amount, $decimals)) {
                // To avoid rounding problems and pass PaymentModule::validateOrder() check.
                $amount = $order->total_paid;
            }

            if ($response->get('operation_type') === 'CREDIT') {
                // This is a refund, set transaction amount to negative.
                $amount = $amount * -1;
            }

            $timestamp = strtotime($response->get('presentation_date').' UTC');

            $number = $response->get('sequence_number') ? $response->get('sequence_number') : '1';
            $trans_id = $number . '-' . $response->get('trans_id');

            $data = array(
                'card_number' => $response->get('card_number'),
                'card_brand' => $response->get('card_brand'),
                'expiry_month' => $response->get('expiry_month'),
                'expiry_year' => $response->get('expiry_year')
            );

            if (! ($pccId = $this->addOrderPayment($order, $invoice, $trans_id, $amount, $timestamp, $data))) {
                return;
            }

            $payment_ids[] = $pccId;
        }

        $payment_ids = implode(', ', $payment_ids);
        $this->logger->logInfo(
            "Payment information with ID(s) {$payment_ids} saved successfully for cart #{$order->id_cart}."
        );
    }

    /**
     * Delete payment and invoice reference.
     *
     * @param OrderPayment $payment
     */
    private function deleteOrderPayment($payment)
    {
        // Delete payment.
        $payment->delete();

        // Delete invoice reference.
        $result = Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'order_invoice_payment` WHERE `id_order_payment` = ' . (int) $payment->id
        );

        if (! $result) {
            $this->logger->logWarning(
                "An error occurred when deleting invoice reference for payment #{$payment->id}:."
            );
        }
    }

    public function saveIdentifier($customer, $response)
    {
        if (! $customer->id) {
            return;
        }

        if ($response->get('identifier') && in_array($response->get('identifier_status'), array('CREATED', 'UPDATED'))) {
            $this->logger->logInfo(
                "Identifier for customer #{$customer->id} successfully "
                ."created or updated on payment gateway. Let's save it and save masked card and expiry date."
            );

            // Mask all card digits unless the last 4 ones.
            $number = $response->get('card_number');
            $masked = '';

            $matches = array();
            if (preg_match('#^([A-Z]{2}[0-9]{2}[A-Z0-9]{10,30})(_[A-Z0-9]{8,11})?$#i', $number, $matches)) {
                // IBAN(_BIC).
                $masked .= isset($matches[2]) ? str_replace('_', '', $matches[2]) . ' / ' : ''; // BIC.

                $iban = $matches[1];
                $masked .= Tools::substr($iban, 0, 4) . str_repeat('X', Tools::strlen($iban) - 8) . Tools::substr($iban, -4);
            } elseif (Tools::strlen($number) > 4) {
                $masked = str_repeat('X', Tools::strlen($number) - 4) . Tools::substr($number, -4);

                if ($response->get('expiry_month') && $response->get('expiry_year')) {
                    // Format card expiration data.
                    $masked .= ' ';
                    $masked .= str_pad($response->get('expiry_month'), 2, '0', STR_PAD_LEFT);
                    $masked .= ' / ';
                    $masked .= $response->get('expiry_year');
                }
            }

            // Save customers configuration as array: n = identifier, m = masked PAN.
            $customers_config = @unserialize(Configuration::get('LYRA_CUSTOMERS_CONFIG'));
            if (! is_array($customers_config)) {
                $customers_config = array();
            }

            // Parse order_info parameter.
            $parts = explode('&', $response->get('order_info'));

            // Recover module_id.
            $module_id = Tools::substr($parts[0], Tools::strlen('module_id='));
            if (! $module_id) {
                $module_id = 'standard';
            }

            $customers_config[$customer->id][$module_id] = array(
                'n' => $response->get('identifier'),
                'm' => $masked
            );
            Configuration::updateValue('LYRA_CUSTOMERS_CONFIG', serialize($customers_config));

            $this->logger->logInfo(
                "Identifier for customer #{$customer->id} and masked PAN #{$masked} successfully saved."
            );
        }
    }

    private function findOrderPayment($order_ref, $trans_id)
    {
        $payment_id = Db::getInstance()->getValue(
            'SELECT `id_order_payment` FROM `' . _DB_PREFIX_ . 'order_payment`
            WHERE `order_reference` = \'' . pSQL($order_ref) . '\' AND transaction_id = \'' . pSQL($trans_id) . '\''
        );

        if (! $payment_id) {
            return false;
        }

        return new OrderPayment((int) $payment_id);
    }

    private function addOrderPayment($order, $invoice, $trans_id, $amount, $timestamp, $data)
    {
        $date = date('Y-m-d H:i:s', $timestamp);

        if (! ($pcc = $this->findOrderPayment($order->reference, $trans_id))) {
            // Order payment not created yet, let's create it.

            $method = sprintf($this->l('%s payment'), $data['card_brand']);
            if (! $order->addOrderPayment($amount, $method, $trans_id, null, $date, $invoice)
                || ! ($pcc = $this->findOrderPayment($order->reference, $trans_id))) {
                $this->logger->logWarning(
                    "Error: payment information for cart #{$order->id_cart} cannot be saved.
                     Error may be caused by another module hooked on order update event."
                );
                return false;
            }
        } elseif (Validate::isLoadedObject($invoice)) {
            $result = Db::getInstance()->execute(
                'REPLACE INTO `' . _DB_PREFIX_ . 'order_invoice_payment`
                 VALUES(' . (int) $invoice->id . ', ' . (int) $pcc->id . ', ' . (int) $order->id . ')'
            );

            if (! $result) {
                $this->logger->logWarning(
                    "An error has occurred during updating invoice reference for payment #{$pcc->id}."
                );
            }
        }

        // Set card info.
        $pcc->card_number = $data['card_number'];
        $pcc->card_brand = $data['card_brand'];
        if ($data['expiry_month'] && $data['expiry_year']) {
            $pcc->card_expiration = str_pad($data['expiry_month'], 2, '0', STR_PAD_LEFT) . '/' . $data['expiry_year'];
        }

        $pcc->card_holder = null;

        // Update transaction info if payment is modified in gateway Back Office.
        $diff = 0;
        if ($pcc->amount != $amount) {
            $diff = $pcc->amount - $amount;
            $pcc->amount = $amount;

            $this->logger->logInfo("Transaction amount is modified for cart #{$order->id_cart}. New amount is $amount.");
        }

        if ($pcc->date_add != $date) {
            $pcc->date_add = $date;

            $this->logger->logInfo("Transaction presentation date is modified for cart #{$order->id_cart}. New date is $date.");
        }

        if ($pcc->update()) {
            if ($diff > 0) {
                $order->total_paid_real -= $diff;
                $order->update();
            }

            return $pcc->id;
        } else {
            $this->logger->logWarning("Problem: payment mean information for cart #{$order->id_cart} cannot be saved.");
            return false;
        }
    }

    public static function filterTransactions($trs)
    {
        $successful_states = array_merge(
            LyraApi::getSuccessStatuses(),
            LyraApi::getPendingStatuses()
        );

        return $trs->{'operation_type'} === 'DEBIT' && in_array($trs->{'trans_status'}, $successful_states);
    }

    public static function nextOrderState($response, $total_refund = false, $outofstock = false)
    {
        if ($response->isAcceptedPayment()) {
            $valid = false;

            switch (true) {
                case $response->isToValidatePayment():
                    // To validate payment order state.
                    $new_state = 'LYRA_OS_TO_VALIDATE';

                    break;
                case $response->isPendingPayment():
                    if (self::isOney($response)) {
                        // Pending Oney confirmation order state.
                        $new_state = 'LYRA_OS_ONEY_PENDING';
                    } else {
                        // Pending authorization order state.
                        $new_state = 'LYRA_OS_AUTH_PENDING';
                    }

                    break;
                default:
                    // Payment successful.

                    if (($response->get('operation_type') === 'CREDIT') && $total_refund) {
                        $new_state = 'PS_OS_REFUND';
                    } elseif (self::isSofort($response) || self::isSepa($response)) {
                        // Pending funds transfer order state.
                        $new_state = 'LYRA_OS_TRANS_PENDING';
                    } else {
                        $new_state = 'PS_OS_PAYMENT';
                        $valid = true;
                    }

                    break;
            }

            if ($outofstock) {
                if ($valid) {
                    $new_state = Configuration::get('PS_OS_OUTOFSTOCK_PAID') ? 'PS_OS_OUTOFSTOCK_PAID' : 'LYRA_OS_PAYMENT_OUTOFSTOCK';
                } else {
                    $new_state = Configuration::get('PS_OS_OUTOFSTOCK_UNPAID') ? 'PS_OS_OUTOFSTOCK_UNPAID' : 'PS_OS_OUTOFSTOCK';
                }
            }
        } elseif ($response->isCancelledPayment() || ($response->getTransStatus() === 'CANCELLED')) {
            $new_state = 'PS_OS_CANCELED';
        } else {
            $new_state = 'PS_OS_ERROR';
        }

        return Configuration::get($new_state);
    }

    /**
     * Return true if order is in a successful state (paid or pending confirmation).
     *
     * @param Order $order
     * @return boolean
     */
    public static function isSuccessState($order)
    {
        $os = new OrderState((int) $order->getCurrentState());
        if (! $os->id) {
            return false;
        }

        if (self::isOutOfStock($order)) {
            return true;
        }

        $s_states = array(
            'PS_OS_PAYMENT',
            'LYRA_OS_TRANS_PENDING',
            'LYRA_OS_TO_VALIDATE',
            'LYRA_OS_ONEY_PENDING',
            'LYRA_OS_AUTH_PENDING'
        );

        // If state is one of supported states or custom state with paid flag.
        return self::isStateInArray($os->id, $s_states) || (bool) $os->paid;
    }

    public static function isOutOfStock($order)
    {
        $state = $order->getCurrentState();
        $oos_states = array(
            'PS_OS_OUTOFSTOCK_UNPAID', // Override pending states since PrestaShop 1.6.1.
            'PS_OS_OUTOFSTOCK_PAID', // Override paid state since PrestaShop 1.6.1.
            'PS_OS_OUTOFSTOCK', // Considered as pending by module for PrestaShop < 1.6.1.
            'LYRA_OS_PAYMENT_OUTOFSTOCK' // Paid state for PrestaShop < 1.6.1.
        );

        return self::isStateInArray($state, $oos_states);
    }

    public static function isPaidOrder($order)
    {
        $os = new OrderState((int) $order->getCurrentState());
        if (! $os->id) {
            return false;
        }

        // Final states.
        $paid_states = array(
            'PS_OS_OUTOFSTOCK_PAID', // Override paid state since PrestaShop 1.6.1.
            'LYRA_OS_PAYMENT_OUTOFSTOCK', // Paid state for PrestaShop < 1.6.1.
            'PS_OS_PAYMENT',
            'LYRA_OS_TRANS_PENDING'
        );

        return self::isStateInArray($os->id, $paid_states) || (bool) $os->paid;
    }

    public static function getManagedStates()
    {
        $managed_states = array(
            'PS_OS_OUTOFSTOCK_UNPAID', // Override pending state since PrestaShop 1.6.1.
            'PS_OS_OUTOFSTOCK_PAID', // Override paid state since PrestaShop 1.6.1.
            'PS_OS_OUTOFSTOCK', // Considered as pending by module for PrestaShop < 1.6.1.
            'LYRA_OS_PAYMENT_OUTOFSTOCK', // Paid state for PrestaShop < 1.6.1.

            'PS_OS_PAYMENT',
            'LYRA_OS_ONEY_PENDING',
            'LYRA_OS_TRANS_PENDING',
            'LYRA_OS_AUTH_PENDING',
            'LYRA_OS_TO_VALIDATE',
            'PS_OS_ERROR',
            'PS_OS_CANCELED',
            'PS_OS_REFUND'
        );

        return $managed_states;
    }

    public static function hasAmountError($order)
    {
        $orders = Order:: getByReference($order->reference);
        $total_paid = 0;

        // Browse sister orders (orders with the same reference).
        foreach ($orders as $sister_order) {
            $total_paid += $sister_order->total_paid;
        }

        return number_format($total_paid, 2) != number_format($order->total_paid_real, 2);
    }

    public static function isStateInArray($state_id, $state_names)
    {
        if (is_string($state_names)) {
            $state_names = array($state_names);
        }

        foreach ($state_names as $state_name) {
            if (! is_string($state_name) || ! Configuration::get($state_name)) {
                continue;
            }

            if ((int) $state_id === (int) Configuration::get($state_name)) {
                return true;
            }
        }

        return false;
    }

    public static function isOney($response)
    {
        return in_array($response->get('card_brand'), array('ONEY', 'ONEY_SANDBOX', 'ONEY_3X_4X'));
    }

    public static function isSofort($response)
    {
        return $response->get('card_brand') === 'SOFORT_BANKING';
    }

    public static function isSepa($response)
    {
        return $response->get('card_brand') === 'SDD';
    }
}
