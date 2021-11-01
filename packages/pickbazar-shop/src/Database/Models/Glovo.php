<?php

namespace PickBazar\Database\Models;

use Illuminate\Database\Eloquent\Model;
use PickBazar\Database\Models\Order;


class Glovo
{

    private $key;
    private $secret;
    private $mode;
    private $url;

    function __construct($key, $secret, $mode)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->mode = $mode;
        if($mode != "sandbox"){
            $this->url = 'https://api.glovoapp.com/b2b';
        }else{
            $this->url = 'https://stageapi.glovoapp.com/b2b';
        }
    }
    function estimateOrder($order)
    {
        $headers = array('Content-Type: application/json','Authorization:Basic '.base64_encode($this->key.':'.$this->secret));
        
        $data = array(
            'description' => $order['description'],
            'addresses'   => [
                array(
                    'type'  => 'PICKUP',
                    'lat'   => $order['pickup']['lat'],
                    'lon'   => $order['pickup']['lng'],
                    'label' => $order['pickup']['label'],
                ),
                array(
                    'type'  => 'DELIVERY',
                    'lat'   => $order['delivery']['lat'],
                    'lon'   => $order['delivery']['lng'],
                    'label' => $order['delivery']['label']
                )]
        );



        $data = json_encode($data);

        $ch = curl_init($this->url.'/orders/estimate'); 

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        
        $result = curl_exec($ch);
    }

    function createOrder($order)
    {
        $headers = array('Content-Type: application/json','Authorization:Basic '.base64_encode($this->key.':'.$this->secret));
        
        $data = array(
            'description' => $order['description'],
            'reference'   => array('id' => $order['reference']),
            'addresses'   => [
                array(
                    'type'  => 'PICKUP',
                    'lat'   => $order['pickup']['lat'],
                    'lon'   => $order['pickup']['lng'],
                    'label' => $order['pickup']['label'],
                    'details' => $order['pickup']['details'],
                    'contactPhone' => $order['pickup']['phone'],
                    'contactPerson' => $order['pickup']['person'],
                    'instructions' => $order['pickup']['instructions'],
                ),
                array(
                    'type'  => 'DELIVERY',
                    'lat'   => $order['delivery']['lat'],
                    'lon'   => $order['delivery']['lng'],
                    'label' => $order['delivery']['label'],
                    'details' => $order['delivery']['details'],
                    'contactPhone' => $order['delivery']['phone'],
                    'contactPerson' => $order['delivery']['person'],
                    'instructions' => $order['delivery']['instructions']
            
                )]
        );


        if($order['schedule']){
            $data['scheduleTime'] = $order['schedule'];
        }
        
        $data = json_encode($data);
        $ch = curl_init($this->url.'/orders'); 

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        $result = curl_exec($ch);



        if(isset(json_decode($result)->error)){

            $error = json_decode($result)->error->message;
            $code = "0051";
            /*
            switch (json_decode($result)->error->code) {
                case '021400':
                    $error = "Erro: Este cartão excedeu o máximo de tentativas falhadas. Por favor, adiciona outro método de pagamento ao pedido.";
                    $code = "0052";
                    break;
                case 1:
                    $error = "Erro: Este cartão excedeu o máximo de tentativas falhadas. Por favor, adiciona outro método de pagamento ao pedido.";
                    $code = "0053";
                    break;
                case 2:
                    $error = "Erro: Este cartão excedeu o máximo de tentativas falhadas. Por favor, adiciona outro método de pagamento ao pedido.";
                    $code = "0054";
                    break;
            }
            */
            return array(
                "error" => $error,
                "code" => $code
            );

        }else{

            $update = Order::where('tracking_number',$order['reference'])->first();
            $update->shipping_info = json_decode($result);
            $update->save();

            return array(
                "success" => 'Envio criado com successo!',
                "code" => "0050" 
            );
        }

      
    }

    function getOrder($id)
    {
        $headers = array('Content-Type: application/json','Authorization:Basic '.base64_encode($this->key.':'.$this->secret));
        $ch = curl_init($this->url.'/orders/'.$id); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        return $result = curl_exec($ch);
    }

    function cancelOrder($id)
    {
        $headers = array('Content-Type: application/json','Authorization:Basic '.base64_encode($this->key.':'.$this->secret));
        $ch = curl_init($this->url.'/orders/'.$id.'/cancel'); 

        $data = array();
        $data = json_encode($data);
        
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data); 

        $result = curl_exec($ch);

       

    }

    function trackingOrder($id)
    {
        // return array(
        //     'lat'   => "38.7998611",
        //     'lon'   => "-9.1383909",
        // );

        $headers = array('Content-Type: application/json','Authorization:Basic '.base64_encode($this->key.':'.$this->secret));
        $ch = curl_init($this->url.'/orders/'.$id.'/tracking'); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        return $result = curl_exec($ch);


        
    }


    function courierInfo($id)
    {
        // return array(
        //     'courier'   => "Alfonso",
        //     'phone'     => "+34666123123",
        // );

        $headers = array('Content-Type: application/json','Authorization:Basic '.base64_encode($this->key.':'.$this->secret));
        $ch = curl_init($this->url.'/orders/'.$id.'/courier-contact'); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        return $result = curl_exec($ch);
    }


}