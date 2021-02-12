<?php

include 'C:\wamp64\www\VAP-final\config\config.inc.php';
// include 'C:\wamp64\www\VAP\override\controllers\FetchCommandApi.php';

function connectionApi()
{
    $authAPI = _url_connexion_api_interne_DEV_; // URL à modifier si besoin
    $curl = curl_init();
    $username = _username_api_DEV_;
    $password = _password_api_DEV_;

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


function orderData()
{
    $sql = 'SELECT *
        FROM `' . _DB_PREFIX_ . 'orders`
        WHERE `current_state` = 2';

    $sqlRequest = Db::getInstance()->executeS($sql);

    return $sqlRequest;
}


function dateFr($date)
{
    return strftime('%d-%m-%Y', strtotime($date));
}

// FONCTION QUI MET EN FORME LES DATAS POUR ENVOYER API
function dataApi()
{

    $orders = orderData();
    //  var_dump($orders);
    foreach ($orders as $order) {
        // COMMANDE
        // ADDRESS
        $newCustomer = new Customer($order['id_customer']);
        $CustomerArray = json_decode(json_encode($newCustomer), true);
        // print_r($CustomerArray);
        $newAddressDelivery = new Address($order['id_address_delivery']);
        $addressDeliveryArray = json_decode(json_encode($newAddressDelivery), true);
        // // COMMENT 
        // //$GetCustomerMessage = CustomerMessage::getMessagesByOrderId($order->id);
        //TRANSPORT
        $newCarrier = new Carrier($order['id_carrier']);
        $carrierArray = json_decode(json_encode($newCarrier), true);
        // print_r($carrierArray);
        // // PANIER DE PRODUITS
        // $newCart = new Cart($order['id_cart']);
        // $cartArray = json_decode(json_encode($newCart), true);
        //  $cart1 = $newCart->getProducts();
        // // var_dump($cart1);
        // $line_items = array();
        // // LISTE PRODUIT A LA COMMANDE
        // foreach ($cart1 as $product) {
        //     print_r($product['name']);
        //     $line = array(
        //         "line_item" => array(
        //             "line_order_id" => $nbr_line++,
        //             "supplier_reference" => $product['reference'],
        //             "product_id" => $product['ean13'],
        //             "product_description" => $product['name'],
        //             "ordered_qty" => $product['quantity'],
        //             "unit_price" => number_format($product['price'] - $product['ecotax'], 2),
        //             "customer_reference" => "",
        //         ),
        //     );
        //     array_push($line_items, $line);
        // }
        // echo 'fuck u';

        $test = new Cart(26);

        dump("nike ta mere la class CART!!!!!!!!");
        $testArray = json_decode(json_encode($test), true);
        // dump($testArray);
        $testOrder = $test->getProducts(10);
        $line_items = array();
        $nbr_line = 1;
        foreach ($testOrder as $product) {
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
        print_r($line_items);



        //CONVERSION DE DATE US => FR
        $dateDMY = datefr($CustomerArray['date_add']);
        $dateNoForm = substr_replace($CustomerArray['date_add'], $dateDMY, 0, 10);
        $date = str_replace("-", "/", $dateNoForm);

        $header = array(
            "order_id" => $order['reference'],
            "order_type" => 'DRP', //DROPSHIPPING
            "number_of_lines" => $nbr_line - 1,
            "order_weight" => '10', //PAS BESOIN DE CHANGER, RECALCULER DANS BEXT,
            "creation_date" => $date,
            "insurrance_amount" => "",
            "amount_with_vat" => number_format(($order['total_paid_tax_incl'] - $order['total_shipping']), 2),
            "due_date" => $date, //
            "preparation_comment" => '',
            "delivery_comment" => '', //(empty($GetCustomerMessage[0]['message']) ? '' : $GetCustomerMessage[0]['message']),
            "delivery_amount_ht" => (empty($order['total_shipping_tax_excl']) ? '' : number_format($order['total_shipping_tax_excl'], 2))
        );

        $transportation = array(
            "carrier_id" => $newCarrier->id_reference,
            "carrier_name" => $newCarrier->name,
            "pickup_id" => '',
            "pickup_network" => '',
        );

        // TABLEAU DES INFO CLIENT ET LIVRAISON
        $addressDelivery = array(
            "language" => 'FR',
            "last_name" => $newCustomer->lastname,
            "first_name" => $newCustomer->firstname,
            "company_name" => '',
            "adress_1" => $newAddressDelivery->address1,
            "adress_2" => $newAddressDelivery->address2,
            "adress_3" => '',
            "other_info" => '',
            "postcode" => $newAddressDelivery->postcode,
            "city" => $newAddressDelivery->city,
            "country_code" => 'FR',
            "phone" => $newAddressDelivery->phone,
            "mobile_phone" => $newAddressDelivery->phone_mobile,
            "email" => $newCustomer->email,
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
        print_r($my_order);
        return $my_order;
    }
}
dataApi();


// // FONCTION ENVOIE DATA SUR BEXT
// function sendDataBext()
// {
// }

// // FONCTION QUI UPDATE LE STATUt "PAIEMENT ACCEPTEE" -> "SEND BEXT" 
// function updateStatusPaidToSendBext()
// {
// }
