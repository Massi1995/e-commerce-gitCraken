<?php

 Class SendApi extends DbPDO {
     public static function getOrderIdsByStatus($id_order_state)
     {
         $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
 SELECT id_order
 FROM '._DB_PREFIX_.'orders o
 WHERE '.(int)$id_order_state.' = (
  SELECT id_order_state
  FROM '._DB_PREFIX_.'order_history oh
  WHERE oh.id_order = o .id_order
  ORDER BY date_add DESC, id_order_history DESC
  LIMIT 1
 )
 ORDER BY invoice_date ASC');
         $orders = array();
         foreach ($result AS $order)
             $orders[] = (int)($order['id_order']);
         return $orders;
     }
     public function postOrderToApi($order)
     {

         // COMMANDE
         $orderArray = json_decode(json_encode($order), true);
         // ADDRESS
         $dataCustomer = new Customer($order->id_customer);
         $addressDelivery = new Address($order->id_address_delivery) ;
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
                     "unit_price" => number_format($product['price']-$product['ecotax'],2) ,
                     "customer_reference" => "",
                 ),
             );
             array_push($line_items, $line);
         }

         //CONVERSION DE DATE US => FR
         $dateDMY =$this->datefr($orderArray['date_add']);
         $dateNoForm=substr_replace($orderArray['date_add'],$dateDMY,0,10);
         $date=str_replace("-","/",$dateNoForm);

         $header = array(
             "order_id" => $orderArray['reference'],
             "order_type" => 'DRP',
             "number_of_lines" => $nbr_line-1,
             "order_weight" =>'10', //,
             "creation_date" => $date,
             "insurrance_amount" => "",
             "amount_with_vat" =>($orderArray['total_paid_tax_incl']-$orderArray['total_shipping']) ,#number_format($firstOrder['total_price_tax_incl'],2),
             "due_date" => $date,//
             "preparation_comment" => '',
             "delivery_comment" => '',//(empty($GetCustomerMessage[0]['message']) ? '' : $GetCustomerMessage[0]['message']),
             "delivery_amount_ht"=>(empty($orderArray['total_shipping_tax_excl']) ? '' : number_format($orderArray['total_shipping_tax_excl'],2))
         );
         $transportation=array(
             "carrier_id" => '1',
             "carrier_name" =>'',
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
                 "transportation" =>$transportation,
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

             $res= curl_exec($curlPostOrder);
             dump('postFin api '.time());
             dump($res);

             $resArr =json_decode($res,true);
             $codeHTTP=$resArr['code'];
             $msgRetour = $resArr['Reason'];

             #$resultatPostApi= curl_getinfo($curlPostOrder,CURLINFO_HTTP_CODE);
             $reponseApi=  array(
                 "codeRetour" => $codeHTTP,
                 "msgRetour"  => $msgRetour,
                 "numCommande" => $orderArray['reference'],
                 "jsonOrder"  => $jsonorder,
             );

             curl_close($curlPostOrder);
             curl_close($curl);

             return $reponseApi;
         }
     }

 }