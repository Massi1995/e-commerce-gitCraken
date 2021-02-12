<?php
//config utiliser dans le fichier UpdateStatutOrder
//include('../../config/defines.inc.php');

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

