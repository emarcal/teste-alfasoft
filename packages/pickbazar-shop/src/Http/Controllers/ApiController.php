<?php

namespace PickBazar\Http\Controllers;

use Illuminate\Http\Request;
use PickBazar\Database\Models\Order;
use PickBazar\Database\Models\Settings;
use PickBazar\Database\Models\User;
use PickBazar\Database\Models\AttributeProduct;
use PickBazar\Database\Models\Attribute;
use PickBazar\Database\Models\AttributeValue;
use PickBazar\Database\Models\VariationOption;
use PickBazar\Http\Controllers\SettingsController;
use PickBazar\Database\Repositories\SettingsRepository;

class ApiController extends CoreController
{

    public function orderZS($order)
    {
        $order = Order::where('tracking_number',$order)->first();
                $user = User::where('id',$order->customer_id)->first();
                if($order->delivery_hour){
                    $delivery_hour = $order->delivery_hour." ";
                }else{
                    $delivery_hour = "";
                }
                switch($order->payment_gateway){
                    case 'stripe':
                    $order->payment_gateway = 'card';
                    break;
                }
                $products = [];
                foreach($order->products as $product){
                    $variation = [];
                    $attribute_product = AttributeProduct::where('product_id',$product->id)->get();
                    if($product->pivot->variation_option_id){
                        $variation_option = VariationOption::where('id',$product->pivot->variation_option_id)->first();
                        foreach(json_decode($variation_option->options,true) as $ap){
                            if($ap['price'] > 0){
                                $price = $ap['price']*100;
                            }else{
                                $price = null;
                            }
                            $variation[] = array(
                                'id'=>$ap['id'],
                                'quantity'=>1,
                                'name'=>$ap['value'],
                                'price'=>$price,
                                "discount"=>0
                                //'attribute'=> $atr->name
                            );  
                            array_multisort(array_column($variation, 'id'), SORT_ASC, $variation);
                        }
                        $variation;
                    }else{
                        $variation = NULL;
                    }
                    $products[] = array(
                        "id"                 => $product->sync_id,
                        "quantity"           => $product->pivot->order_quantity,
                        "name"               => $product->name,
                        "price"              => $product->pivot->subtotal*100,
                        "discount"           => 0,
                        "atributes"          => $variation
                    );
                }
                $zs_order = array(
                    "order_id"               => $order->tracking_number,
                    "store_id"               => env('ZONESOFT_CLIENT_ID'),
                    "type_order"             => 'DELIVERY',
                    "order_time"             => date("Y-m-d H:i:s",strtotime($order->created_at)),
                    "estimated_pickup_time"  => date("Y-m-d H:i:s",strtotime($delivery_hour.$order->delivery_time)),
                    "payment_method"         => $order->payment_gateway,
                    "currency"               => "EUR",
                    "allergy_info"           => $order->obs,
                    "delivery_fee"           => $order->delivery_fee*100,
                    "estimated_total_price"  => $order->amount*100,
                    "courier"                => null,
                    "customer"               => array(
                        "name"               => $user->name,
                        "phone_number"       => $order->customer_contact,
                        "nif"                => $order->customer_nif,
                    ),
                    "products"               => $products,
                    
                    "delivery_address"       => array(
                        "label"             => $order->billing_address['street_address']." ".$order->billing_address['state']." ".$order->billing_address['zip']." ".@$order->billing_address['instructions'],
                        "latitude"           => @$order->billing_address['lat'],
                        "longitude"          => @$order->billing_address['lng'],
                    ),
                    "is_picked_up_by_customer" => false,
                    "discounted_products_total" => 0,
                    "total_customer_to_pay" => $order->total*100,
                );

            return $zs_order;
    }
    public function existValue($array,$term,$attr){
        foreach(json_decode($array) as  $a){
            if($a->value == $term && $a->name == $attr){
                return array('price'=>$a->price,'level'=>$a->level);
            }
        }
    }

    public function sendOrderZS($order)
    {
        $curl = curl_init();
        $data = json_encode($order);
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://zsroi.zonesoft.org/v1.0/integration/order',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>$data,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        
        return $response;
    }

}
