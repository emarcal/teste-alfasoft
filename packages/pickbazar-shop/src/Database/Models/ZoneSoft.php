<?php

namespace PickBazar\Database\Models;
use Illuminate\Database\Eloquent\Model;

class ZoneSoft
{

    //Adjust for the current Zonesoft API public version
    
    const ZONESOFT_API_VERSION = "v2.3";
    const ZONESOFT_API_URL = "https://api.zonesoft.org/";


    const ZONESOFT_API_INTERFACE_AUTH = self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/auth/authenticate/";

    const ZONESOFT_API_INTERFACE_UNITS_GET_INSTANCE = self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/Units/getInstance/";

    const ZONESOFT_API_INTERFACE_CHARACTERISTICS_GET_INSTANCE = self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/Characteristics/getInstance/";

    const ZONESOFT_API_INTERFACE_STOCK_IN_INSTANCE = self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/Productstocks/getCurrentStockInstances/";
    const ZONESOFT_API_INTERFACE_STOCK_IN_STORES= self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/Productstocks/getCurrentStockInStores/";

    const ZONESOFT_API_INTERFACE_FAMILIES_GET_INSTANCE = self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/Families/getInstance/";
    const ZONESOFT_API_INTERFACE_FAMILIES_GET_INSTANCES = self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/Families/getInstances/";


    const ZONESOFT_API_INTERFACE_PRODUCTS_GET_INSTANCES = self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/Products/getInstances/";
    const ZONESOFT_API_INTERFACE_PRODUCTS_GET_INSTANCE = self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/Products/getInstance/";

    const ZONESOFT_API_INTERFACE_SUBFAMILIES_GET_INSTANCE = self::ZONESOFT_API_URL . self::ZONESOFT_API_VERSION . "/subfamilies/getInstance/";
    const TIME_OUT = 10;

    private $nif;
    private $username;
    private $password;
    public $store;
    private $series;
    private $docType;
    public $hashCode;
    public $isAuthenticated = false;
    public $storesList = array();
    private $comercialName;
    public $onlineStatus = array();
    public $currency;

    function __construct($nif, $username, $password, $store, $serie, $docType)
    {
        $this->nif = $nif;
        $this->username = $username;
        $this->password = $password;
        $this->store = $store;
        $this->series = $serie;
        $this->docType = $docType;
    }

    function testLogin()
    {
        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_URL, self::ZONESOFT_API_INTERFACE_CMD);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, "n=$this->nif&u=$this->username&p=$this->password&q=select * from anulacoes"); // Define what you want to post
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the output in string format
        $output = curl_exec($ch); // Execute
        curl_close($ch); // Close cURL handl
        print_r($output);
        if ($output == '[]') {
            $this->isAuthenticated = TRUE;
            return true;
        } else {
            $this->isAuthenticated = FALSE;
            return false;
        }
    }

    function getCurrency()
    {
        if ($this->isAuthenticated) {
            $store1 = $this->storesList[1];
            $contry = $store1['pais'];
            switch ($contry) {
                case 'PT':
                    $this->currency = '€';
                    return 1;
                case 'AO':
                    $this->currency = 'Kz';
                    return 2;
                default:
                    $this->currency = '€';
                    return 1;
            }
        }
    }

    function autenticate()
    {
        $data = array("user" => array("nif" => $this->nif,
            "nome" => $this->username,
            "password" => $this->password,
            "loja" => $this->store));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_AUTH);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        // Fetches the status code from api
        $apiResponse = $api_response['Response']['StatusCode'];
        switch ($apiResponse) {
            case '200':
                $this->hashCode = $api_response['Response']['Content']['auth_hash'];
                $this->isAuthenticated = TRUE;
                break;
            case '401':
                $this->hashCode = NULL;
                $this->isAuthenticated = FALSE;
                break;
            default:
                $this->hashCode = NULL;
                $this->isAuthenticated = FALSE;
                break;
        }
        return $this->hashCode;
    }

    function getFamilies()
    {
        $data = array("auth_hash"=>$this->hashCode,
            "family" => array("limit" => 15,
            "order" => "lastupdate;DESC",
        ));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_FAMILIES_GET_INSTANCES);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        return $api_response;

    }

    function getProducts()
    {
        $data = array("auth_hash"=>$this->hashCode,
            "product" => array("limit" => 15,
        ));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_PRODUCTS_GET_INSTANCES);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        // Fetches the status code from api
        return $api_response['Response']['Content']['product'];


    }
    

    function getFamily($code)
    {
        $data = array("auth_hash"=>$this->hashCode,
            "family" => array("codigo" => $code,
                "loja" => 1,
            ));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_FAMILIES_GET_INSTANCE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        return $api_response['Response']['Content']['family'];


    }

    function getUnity($code)
    {
        $data = array("auth_hash"=>$this->hashCode,
            "unit" => array("codigo" => $code,
                "loja" => 1,
            ));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_UNITS_GET_INSTANCE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        return $api_response['Response']['Content']['unit']['descricao'];
    }

    function getImage($code)
    {
        $data = array("auth_hash"=>$this->hashCode,
            "product" => array("codigo" => $code,
                "loja" => 1,
            ));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_PRODUCTS_GET_INSTANCE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        // Fetches the status code from api
        if(isset($api_response)){
            return $api_response['Response']['Content']['product']['foto'];
       }else{
            return null;
       }

       


    }

    function getCharacteristic($code)
    {
        $data = array("auth_hash"=>$this->hashCode,
            "characteristic" => array("id" => $code,
                "loja" => 1,
            ));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_CHARACTERISTICS_GET_INSTANCE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        
        return $api_response['Response']['Content']['characteristic'];


    }

    function getStock($product,$opt)
    {
        if($opt != FALSE){
            return $this->getStockCharacteristics($product,$opt);
        }else{
            return $this->getStockSum($product);
        }
        
    }
    function getStockCharacteristics($product,$option)
    {

        $data = array("auth_hash"=>$this->hashCode,
            "productstock" => array(
                "produto" => $product,
                "loja" => 1,
                "uid_caracteristica" => $option,
                
        ));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_STOCK_IN_INSTANCE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        return $api_response['Response']['Content']['productstock'][0]['stock'];
    }

    function getStockSum($product)
    {

        $data = array("auth_hash"=>$this->hashCode,
            "productstock" => array(
                "produto" => $product,
                "loja" => 1,
                
        ));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_STOCK_IN_STORES);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        if(isset($api_response)){
             $array = $api_response['Response']['Content']['productstock'];
            return array_sum(array_column($array,'stock'));
        }else{
            return 0;
        }
       
    }


    function getSubFamily($code)
    {
        $data = array("auth_hash"=>$this->hashCode,
            "subfamily" => array("codigo" => $code,
            ));
        $json_data = json_encode($data);
        $ch = curl_init(self::ZONESOFT_API_INTERFACE_SUBFAMILIES_GET_INSTANCE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIME_OUT); //timeout in seconds
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'charset=utf-8')
        );
        $result = curl_exec($ch);
        $api_response = json_decode($result, TRUE);
        //var_dump($api_response);
        return $api_response['Response']['Content']['subfamily'];


    }
    
}