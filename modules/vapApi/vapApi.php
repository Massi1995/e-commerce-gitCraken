<?php

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

if (!defined('_PS_VERSION_')) {
    return false;
}
require_once("C:\wamp64\www\VAP-final\modules\/vapApi\/vapApiClass.php");


class VapApi extends Module implements WidgetInterface
{
    private $_html;
    private $templateFile;

    public function __construct()
    {
        $this->name = 'VapApi';
        $this->author = 'Massi Djellouli';
        $this->version = '1.0';

        $this->boostrap = true;
        parent::__construct();
        $this->displayName = $this->trans('Vap Api ', array(), "Modules.VapApi.Admin");
        $this->description = $this->trans('Ce module sert ensentiellement pour gérer l envoi et la remonter des commandes sur bext  ', array(), "Modules.VapApi.Admin");
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        $this->templateFile = 'module:VapApi/views/templates/hook/VapApi.tpl';
    }

    public function install()
    {

        if (!parent::install() || !$this->registerHook('actionAdminControllerSetMedia')) {
            return false;
        }

    }

    public function uninstall()
    {
        return parent::uninstall();

    }

    public function getContent()
    {
        $logo = '<img class="logo_myprestamodules" src="../modules/' . $this->name . '/logo.png" />';
        $name = '<h2 id="bootstrap_orders_export">' . $logo . $this->displayName . '</h2>';

        $this->_html .= $name;

        $this->context->smarty->assign(
            array(
                'location_href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&configure=vapApi',
                'tab' => Tools::getValue('module_tab')
            )
        );
        $output = null;

        $this->_html .= $this->display(__FILE__, 'views/templates/hook/tabs.tpl');
        $this->displayForm();
        return $this->_html;

    }

    public function displayForm()
    {

         if( Tools::getValue('module_tab') == 'Remonter_Status' ){
               $this->updateStatus();
               print_r("tout va béné");
         }
        if( Tools::getValue('module_tab') == 'step_2' ){
            $step = " step_2";
        }
    }

    //FORMAT DATE EN FR
    function dateFr($date)
    {
        return strftime('%d-%m-%Y', strtotime($date));
    }

    //FONCTION DE CONNEXION A L API
    function connectionApi()
    {
        $authAPI = _url_connexion_api_interne_; // URL à modifier si besoin
        $curl = curl_init();
        $username = _username_api_;
        $password = _password_api_;

        //CONNEXION API => crée une fonction qui crée le token si le check du token "expiré"
        curl_setopt_array($curl, array(
            CURLOPT_URL => $authAPI,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_POSTFIELDS => "{\"username\":$username,\"password\":$password}",
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        $token = json_decode($response, true)['access_token'];
        return $token;
    }

    // FONCTION DESCENTE DES COMMANDE VERS L API
    public function postOrderToApi($order)
    {
        // COMMANDE
        $orderArray = json_decode(json_encode($order), true);
        // ADDRESS
        $dataCustomer = new Customer($order->id_customer);
        $addressDelivery = new Address($order->id_address_delivery);
        //COMMENT
        //TRANSPORT
        #$carrierObject = new Carrier ($order->id_carrier);
        // PANIER DE PRODUITS
        $cart = new Cart($order->id_cart);
        $cart1 = $cart->getProducts();
        $line_items = array();
        // LISTE PRODUIT A LA COMMANDE
        $nbr_line = 1;

        foreach ($cart1 as $product) {
            $line = array(
                "line_item" => array(
                    "line_order_id" => $nbr_line++,
                    "supplier_reference" => $product['reference'],
                    "product_id" => $product['ean13'],
                    "product_description" => $product['name'],
                    "ordered_qty" => $product['quantity'],
                    "unit_price" => number_format($product['price'] - $product['ecotax'], 2),
                    "customer_reference" => "",
                ),
            );
            array_push($line_items, $line);
        }

        //CONVERSION DE DATE US => FR
        $dateDMY = $this->datefr($orderArray['date_add']);
        $dateNoForm = substr_replace($orderArray['date_add'], $dateDMY, 0, 10);
        $date = str_replace("-", "/", $dateNoForm);

        $header = array(
            "order_id" => $orderArray['reference'],
            "order_type" => 'DRP',
            "number_of_lines" => $nbr_line - 1,
            "order_weight" => '10', //,
            "creation_date" => $date,
            "insurrance_amount" => "",
            "amount_with_vat" => ($orderArray['total_paid_tax_incl'] - $orderArray['total_shipping']),#number_format($firstOrder['total_price_tax_incl'],2),
            "due_date" => $date,//
            "preparation_comment" => '',
            "delivery_comment" => '',//(empty($GetCustomerMessage[0]['message']) ? '' : $GetCustomerMessage[0]['message']),
            "delivery_amount_ht" => (empty($orderArray['total_shipping_tax_excl']) ? '' : number_format($orderArray['total_shipping_tax_excl'], 2))
        );
        $transportation = array(
            "carrier_id" => '1',
            "carrier_name" => '',
            "pickup_id" => '',
            "pickup_network" => '',
        );

        // TABLEAU DES INFO CLIENT ET LIVRAISON
        $addressDelivery = array(
            "language" => 'FR',
            "last_name" => $dataCustomer->lastname,
            "first_name" => $dataCustomer->firstname,
            "company_name" => '',
            "adress_1" => $addressDelivery->address1,
            "adress_2" => $addressDelivery->address2,
            "adress_3" => '',
            "other_info" => '',
            "postcode" => $addressDelivery->postcode,
            "city" => $addressDelivery->city,
            "country_code" => 'FR',
            "phone" => $addressDelivery->phone,
            "mobile_phone" => $addressDelivery->phone_mobile,
            "email" => $dataCustomer->email,
        );

        // LE TABLEAU ASSOCIATIF DE LA COMMANDE ENVOYEE A L API
        $my_order = array(
            "order" => array(
                "header" => $header,
                "transportation" => $transportation,
                "shipping" => $addressDelivery,
                "billing" => $addressDelivery,
                "line_items" => $line_items
            ),
        );
        #dump('3 tab assoc '.time());
        $jsonorder = json_encode($my_order);
        $username = _username_api_;
        $password = _password_api_;

        $curl = curl_init();

        //CONNEXION API
        curl_setopt_array($curl, array(
            CURLOPT_URL => _url_connexion_api_interne_,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_POSTFIELDS => "{\"username\":$username,\"password\":$password}",
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);

        $access_token = json_decode($response, true)['access_token'];
        if ($response) {
            //Descente des commandes vers l'api
            $curlPostOrder = curl_init();
            curl_setopt_array($curlPostOrder, array(
                CURLOPT_URL => _url_post_api_interne_,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $jsonorder,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                    "Authorization: Bearer $access_token",
                )
            ));

            $res = curl_exec($curlPostOrder);

            $resArr = json_decode($res, true);
            $codeHTTP = $resArr['code'];
            $msgRetour = $resArr['Reason'];

            #$resultatPostApi= curl_getinfo($curlPostOrder,CURLINFO_HTTP_CODE);
            $reponseApi = array(
                "codeRetour" => $codeHTTP,
                "msgRetour" => $msgRetour,
                "numCommande" => $orderArray['reference'],
                "jsonOrder" => $jsonorder,
            );

            curl_close($curlPostOrder);
            curl_close($curl);

            return $reponseApi;
        }
    }

    // FONCTION DE REMONTER DE STATUS POUR UNE COMMANDE

    function getStatus($idOrder, $token)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://rec-blueway2:8543/engine54/52/PortailJSON?flowName=API_BW_Orders_Status&flowType=EAII&actionJSON=launch&orderid=' .$idOrder,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_SSL_VERIFYPEER => 0, CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                "Authorization: Bearer $token"
            ),
        ));

        $response = curl_exec($curl);
        $idStatus = json_decode($response, true);
        curl_close($curl);

        if (empty($idStatus)) {
            $c = 10;
        } else {
            $idStatu = $idStatus ["orders"];
            $a = $idStatu[0];
            $b = $a["order"];
            $c = $b["status_code"];
        }
        return $c;
    }


    //FONCTION POUR METTRE A JOUR LES STATUS DES COMMANDES
    function updateStatus()
    {

        $sql = 'SELECT `id_order`,`reference`,`current_state`, `date_upd`,`id_customer`
                     FROM `' . _DB_PREFIX_ . 'orders`';
        $Orders = Db::getInstance()->executeS($sql);
        $token = $this->connectionApi();

        foreach ($Orders as $row) {
            $idstatus = $this->getStatus($row['reference'], $token);
            switch ((int)$idstatus) {
                case 0:
                    //STATUS RECU SUR BEXT
                    Db::getInstance()->update('orders', array(
                        'current_state' => 24,
                    ), 'id_order=' .$row["id_order"]);
                    break;

                case 1:
                    //STATUS ENVOYEE SUR BEXT
                    Db::getInstance()->update('orders', array(
                        'current_state' => 20,
                    ), 'id_order =' .$row["id_order"]);
                    break;

                case 2:
                    //STATUS ACCEPTER PAR BEXT
                    Db::getInstance()->update('orders', array(
                        'current_state' => 26,
                    ), 'id_order=' .$row["id_order"]);
                    break;
                case 7:
                    //STATUS EN PREPARATION
                    if ($row["current_state"]!=3) {
                        $client = new Customer($row["id_customer"]);
                        $mail_client=$client->email;
                          //ENVOI DE MAIL DE CHANGEMENT DE STATUS
                        Mail::send((int)(Configuration::get('PS_LANG_DEFAULT')),
                            'in_transit', //need to change the template directory to point to custom module
                            'Subject',
                            array(
                                '{firstname}'=>$client->firstname,
                                '{lastname}'=>$client->lastname,
                            ),
                            "$mail_client",
                            '.$client->firstname.',
                            null,
                            null,
                            null,
                            null,
                            'VAP-final/mails/fr/in_transit.html'

                        );
                        Db::getInstance()->update('orders', array(
                            'current_state' => 3,
                        ), 'id_order=' . $row["id_order"]);

                    }
                    break;
                case 8:
                    //PRETE A L'EXPEDITION
                    //ENVOI DE MAIL DE CHANGEMENT DE STATUS
                    Db::getInstance()->update('orders', array(
                        'current_state' => 25,
                    ), 'id_order=' .$row["id_order"]);
                    break;
                case 9:
                    //EXPIDIEE
                    if ($row["current_state"]!=4) {
                        $client = new Customer($row["id_customer"]);
                        $mail_client=$client->email;

                        Mail::send((int)(Configuration::get('PS_LANG_DEFAULT')),
                            'in_transit', //need to change the template directory to point to custom module
                            'Subject',
                            array(
                                '{firstname}'=>$client->firstname,
                                '{lastname}'=>$client->lastname,
                            ),
                            "$mail_client",
                            '.$client->firstname.',
                            null,
                            null,
                            null,
                            null,
                            'VAP-final/mails/fr/in_transit.html'

                        );

                        Db::getInstance()->update('orders', array(
                            'current_state' => 4,
                        ), 'id_order=' . $row["id_order"]);
                    }
                    break;
                default :

            }

        }

    }


    public function renderWidget($hookName, array $configuration)
    {
        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        return $this->fetch($this->templateFile);
    }

    public function getWidgetVariables($hookName, array $configuration)
    {   //handle form submission
        if (Tools::isSubmit('comment')) {
            $VapApi = new VapApiClass();
            $VapApi->comment = Tools::getValue('comment');
            $VapApi->id_comment = Tools::getValue('id_comment');
            $VapApi->product_id = Tools::getValue('product_id');
            $VapApi->user_id = Tools::getValue('user_id');
            $VapApi->save();

        }
        return array(
            'message' => "hello this product is great"
        );
    }

    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('configure') == "vapApi") {
            $this->context->controller->addCSS(_PS_BASE_URL_SSL_ . __PS_BASE_URI__ . 'modules/vapApi/views/css/simpleimportproduct.css?v=' . $this->version);
            $this->context->controller->addCSS(_PS_BASE_URL_SSL_ . __PS_BASE_URI__ . 'modules/vapApi/views/css/error.css?v=' . $this->version);
            $this->context->controller->addCSS(_PS_BASE_URL_SSL_ . __PS_BASE_URI__ . 'modules/vapApi/views/css/icons.css?v=' . $this->version);
            $this->context->controller->addJqueryUI('ui.sortable');
            $this->context->controller->addJS(_PS_BASE_URL_SSL_ . __PS_BASE_URI__ . 'modules/vapApi/views/js/simpleimportproduct.min.js?v=1' . $this->version);
            $this->context->controller->addJS(_PS_BASE_URL_SSL_ . __PS_BASE_URI__ . 'modules/vapApi/views/js/error.min.js?v=1' . $this->version);
        }
    }

}
