<?php

class CheckConnexionApi
{
    function checkAccessApi()
    {
        $result = false;
        $username = _username_api_;
        $password = _password_api_;
        $curl = curl_init();

        //CONNEXION API
        curl_setopt_array($curl, array(
            CURLOPT_URL => _url_connexion_api_public_,
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
        curl_exec($curl);
        if(curl_getinfo($curl,CURLINFO_HTTP_CODE)==200){
            $result =true;
            
            return $result;
        }
    }
}