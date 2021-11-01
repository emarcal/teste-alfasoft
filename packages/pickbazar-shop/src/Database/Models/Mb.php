<?php

namespace PickBazar\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Mb 
{
    //ENDPOINTS
    const ENDPOINT_MB_GET_PAYMENTS = 'https://www.ifthenpay.com/IfmbWS/WsIfmb.asmx/GetPaymentsJsonV2';


    const MBWAY_API_KEY = 'PSD-366462';
    const IF_THEN_PAY_ENTITY_ID = 12376;
    const IF_THEN_PAY_SUB_ENTITY_ID = 864;
    const BACKOFFICE_KEY = '3159-8678-2158-3659';


    public function __construct() 
    {

    }

    //Multibanco References
    function format_number($number)
    {
        $verifySepDecimal = number_format(99,2);

        $valorTmp = $number;

        $sepDecimal = substr($verifySepDecimal, 2, 1);

        $hasSepDecimal = True;

        $i=(strlen($valorTmp)-1);

        for($i;$i!=0;$i-=1)
        {
            if(substr($valorTmp,$i,1)=="." || substr($valorTmp,$i,1)==","){
                $hasSepDecimal = True;
                $valorTmp = trim(substr($valorTmp,0,$i))."@".trim(substr($valorTmp,1+$i));
                break;
            }
        }

        if($hasSepDecimal!=True){
            $valorTmp=number_format($valorTmp,2);

            $i=(strlen($valorTmp)-1);

            for($i;$i!=1;$i--)
            {
                if(substr($valorTmp,$i,1)=="." || substr($valorTmp,$i,1)==","){
                    $hasSepDecimal = True;
                    $valorTmp = trim(substr($valorTmp,0,$i))."@".trim(substr($valorTmp,1+$i));
                    break;
                }
            }
        }

        for($i=1;$i!=(strlen($valorTmp)-1);$i++)
        {
            if(substr($valorTmp,$i,1)=="." || substr($valorTmp,$i,1)=="," || substr($valorTmp,$i,1)==" "){
                $valorTmp = trim(substr($valorTmp,0,$i)).trim(substr($valorTmp,1+$i));
                break;
            }
        }

        if (strlen(strstr($valorTmp,'@'))>0){
            $valorTmp = trim(substr($valorTmp,0,strpos($valorTmp,'@'))).trim($sepDecimal).trim(substr($valorTmp,strpos($valorTmp,'@')+1));
        }

        return $valorTmp;
    }

    public function create_mb_reference($order_id, $order_hash, $order_value)
    {
        $chk_val = 0;
        $order_id = sprintf('%04d', $order_id);
        

        $choosed = 0;
        while ($choosed != 1){

            if(strlen($this::IF_THEN_PAY_ENTITY_ID)<5)
            {
                echo "Lamentamos mas tem de indicar uma entidade válida";
                return;
            }else if(strlen($this::IF_THEN_PAY_ENTITY_ID)>5){
                echo "Lamentamos mas tem de indicar uma entidade válida";
                return;
            }if(strlen($this::IF_THEN_PAY_SUB_ENTITY_ID)==0){
            echo "Lamentamos mas tem de indicar uma subentidade válida";
            return;
            }

            $order_value= sprintf("%01.2f", $order_value);

            $order_value =  $this->format_number($order_value);

            if ($order_value < 1){
                echo "Lamentamos mas é impossível gerar uma referência MB para valores inferiores a 1 Euro";
                return;
            }
            if ($order_value >= 1000000){
                echo "<b>AVISO:</b> Pagamento fraccionado por exceder o valor limite para pagamentos no sistema Multibanco<br>";
            }

            if(strlen($this::IF_THEN_PAY_SUB_ENTITY_ID)==1){
                //Apenas sao considerados os 6 caracteres mais a direita do order_id
                $order_id = substr($order_id, (strlen($order_id) - 6), strlen($order_id));
                $chk_str = sprintf('%05u%01u%06u%08u', $this::IF_THEN_PAY_ENTITY_ID, $this::IF_THEN_PAY_SUB_ENTITY_ID, $order_id, round($order_value*100));
            }else if(strlen($this::IF_THEN_PAY_SUB_ENTITY_ID)==2){
                //Apenas sao considerados os 5 caracteres mais a direita do order_id
                $order_id = substr($order_id, (strlen($order_id) - 5), strlen($order_id));
                $chk_str = sprintf('%05u%02u%05u%08u', $this::IF_THEN_PAY_ENTITY_ID, $this::IF_THEN_PAY_SUB_ENTITY_ID, $order_id, round($order_value*100));
            }else {
                //Apenas sao considerados os 4 caracteres mais a direita do order_id
                $order_id = substr($order_id, (strlen($order_id) - 4), strlen($order_id));
                $chk_str = sprintf('%05u%03u%04u%08u', $this::IF_THEN_PAY_ENTITY_ID, $this::IF_THEN_PAY_SUB_ENTITY_ID, $order_id, round($order_value*100));
            }

            //cálculo dos check digits

            $chk_array = array(3, 30, 9, 90, 27, 76, 81, 34, 49, 5, 50, 15, 53, 45, 62, 38, 89, 17, 73, 51);

            for ($i = 0; $i < 20; $i++)
            {
                $chk_int = substr($chk_str, 19-$i, 1);
                $chk_val += ($chk_int%10)*$chk_array[$i];
            }

            $chk_val %= 97;

            $chk_digits = sprintf('%02u', 98-$chk_val);

            // Criar nova referencia Multibanco
            $checkMultibanco = Http::get(env('SYSTEM_URL').'/8CaoSX5fJ', [
                'order' => $order_hash,
                'store' => env('SYSTEM_MERCHANT_ID'),
                'entidade' => $this::IF_THEN_PAY_ENTITY_ID,
                'referencia' => substr($chk_str, 5, 3).substr($chk_str, 8, 3).substr($chk_str, 11, 1).$chk_digits,
                'valor' => $order_value,
                'key'=>'92e7dd7306dbdd412c8d6b626b7c808f0c3fc692c9297aedf047ae918b11be58',
            ]);

            if(json_decode($checkMultibanco) == 'success'){

                $choosed = 1;
                return $array = [
                    "tracking_number" => $order_hash,
                    "entity" => $this::IF_THEN_PAY_ENTITY_ID,
                    "reference" => substr($chk_str, 5, 3)." ".substr($chk_str, 8, 3)." ".substr($chk_str, 11, 1).$chk_digits,
                    "value" => " ".number_format($order_value, 2,',', ' '),
                ];

                
            }

        }


    }

    public function get_mb_reference_state($reference,$payment_value,$dt_begin,$dt_end,$sandbox_mode)
    {

        $post_data = "chavebackoffice=".Manager::BACKOFFICE_KEY.
            "&entidade=".Manager::IF_THEN_PAY_ENTITY_ID.
            "&subentidade=".Manager::IF_THEN_PAY_SUB_ENTITY_ID.
            "&dtHrInicio=".$dt_begin.
            "&dtHrFim=".$dt_end.
            "&referencia=".$reference.
            "&valor=".$payment_value.
            "&sandbox=". $sandbox_mode
        ;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Manager::ENDPOINT_MB_GET_PAYMENTS);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public  function  get_mb_payments(){

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, Manager::ENDPOINT_MB_GET_PAYMENTS);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            "Content-Type: application/x-www-form-urlencoded",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $data = "chavebackoffice=".Manager::BACKOFFICE_KEY.
            "&entidade=".Manager::IF_THEN_PAY_ENTITY_ID.
            "&subentidade=".Manager::IF_THEN_PAY_SUB_ENTITY_ID.
            "&dtHrInicio="."".
            "&dtHrFim="."".
            "&referencia="."".
            "&valor="."0".
            "&sandbox=". 0
        ;
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

}



 