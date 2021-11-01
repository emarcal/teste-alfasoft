<?php

namespace PickBazar\Database\Models;

use Illuminate\Database\Eloquent\Model;

class Mbway
{
    public $api_key;
    public $endpoint_url = 'https://www.ifthenpay.com/mbwayWS/IfthenPayMBW.asmx/SetPedidoJSON';

    public function __construct($key)
    {
        $this->api_key = $key;
    }

    public function callIfthenpayMbWayAPI($referencia, $nr_encomenda, $email, $nr_tlm, $valor)
    {

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $this->endpoint_url . '?MbWayKey=' . $this->api_key . '&canal=03&referencia=' . $nr_encomenda . '&valor=' . $valor . '&nrtlm=' . $nr_tlm . '&descricao=PAGAMENTO' . '&email='.$email
        ));
        $data = curl_exec($curl);
        curl_close($curl);

        return $data;
    }

}
