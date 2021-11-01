<?php

namespace PickBazar\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PickBazar\Http\Requests\CreateShippingRequest;
use PickBazar\Http\Requests\UpdateShippingRequest;
use PickBazar\Database\Repositories\ShippingRepository;
use Prettus\Validator\Exceptions\ValidatorException;
use PickBazar\Database\Models\Glovo;
use PickBazar\Database\Models\Order;
use PickBazar\Database\Models\User;
use PickBazar\Database\Models\Courier;
use PickBazar\Database\Models\Twilio;
use PickBazar\Database\Models\Settings;

class ShippingController extends CoreController
{
    public $repository;

    public function __construct(ShippingRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Collection|Type[]
     */
    public function index(Request $request)
    {
        return $this->repository->orderBy('id','asc')->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateShippingRequest $request
     * @return LengthAwarePaginator|Collection|mixed
     * @throws ValidatorException
     */
    public function store(CreateShippingRequest $request)
    {
        $validateData = $request->validated();
        return $this->repository->create($validateData);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id)
    {
        try {
            return $this->repository->findOrFail($id);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Sorry! Shipping not found.'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param CreateShippingRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateShippingRequest $request, $id)
    {
        try {
            $validateData = $request->validated();
            return $this->repository->findOrFail($id)->update($validateData);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Sorry! Shipping ID does not exist.'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Sorry! Shipping ID does not exist.'], 404);
        }
    }


    public function glovoEstimate($id)
    {
        $order = Order::where('tracking_number',$id)->first();
        $order = array(
            'description' => "Serviço de Entrega - ".Settings::find(1)->options['siteTitle'],
            'pickup'   => 
                array(
                    'lat'   => $order->shipping_address['lat'],
                    'lng'   => $order->shipping_address['lng'],
                    'label' => $order->shipping_address['street_address'],
                ),
            'delivery'   => 
                array(
                    'lat'   => $order->billing_address['lat'],
                    'lng'   => $order->billing_address['lng'],
                    'label' => $order->billing_address['street_address']." ".$order->billing_address['city'],
                ),
        );
        $glovo = new Glovo(env('GLOVO_API'),env('GLOVO_API_SECRET'),'production');
        return $glovo->estimateOrder($order);
    }

    public function glovoConfirm($id)
    {
        $getOrder = Order::where('tracking_number',$id)->first();
        if($getOrder){
            if(isset($getOrder['delivery_hour'])){
                $delivery_hour = " ".$getOrder['delivery_hour'];
            }else{
                $delivery_hour = " ".date('H:i');
            }
            if(strtotime($getOrder['delivery_time'].$delivery_hour)  >  strtotime(date('Y-m-d'))){
                $schedule = strtotime($getOrder['delivery_time'].$delivery_hour)*1000;
            }else{
                $schedule = "";
            }
            $store = Settings::find(1);
            $order = array(
                'reference' => $id,
                'schedule' => $schedule,
                'description' => "Serviço de Entrega - ".$store->options['siteTitle'],
                'pickup'   => 
                    array(
                        'lat'   => $getOrder->shipping_address['lat'],
                        'lng'   => $getOrder->shipping_address['lng'],
                        'label' => $getOrder->shipping_address['street_address'],
                        'details' => $getOrder->shipping_address['state'],
                        'phone' => $store->options['site']['phone'],
                        'person' => $store->options['siteTitle'],
                        'instructions' => $getOrder->shipping_address['instructions'],
                    ),
                'delivery'   => 
                    array(
                        'lat'   => $getOrder->billing_address['lat'],
                        'lng'   => $getOrder->billing_address['lng'],
                        'label' => $getOrder->billing_address['street_address']." ".$getOrder->billing_address['city'],
                        'details' => $getOrder->billing_address['state'],
                        'phone' => $getOrder->customer_contact,
                        'person' => User::where('id',$getOrder->customer_id)->first()->name,
                        'instructions' => $getOrder->billing_address['instructions'],
                    ),
            );
            $glovo = new Glovo(env('GLOVO_API'),env('GLOVO_API_SECRET'),'production');
            if(empty($getOrder->shipping_info)){
                return $glovo->createOrder($order);
            }elseif(json_decode($glovo->getOrder($getOrder->shipping_info['id']))->state == "CANCELED"){
                return $glovo->createOrder($order);       
            }
        }else{
            return array(
                "error" => 'Order not Found!',
                "code" => "0050" 
            );
        }
    }

    public function glovoOrder($id)
    { 
        $order = Order::where('tracking_number',$id)->first();
        $order = $order->shipping_info['id'];
        $glovo = new Glovo(env('GLOVO_API'),env('GLOVO_API_SECRET'),'production');
        return $glovo->getOrder($order);
    }

    public function glovoTracking($id)
    { 
        $order = Order::where('tracking_number',$id)->first();
        $order = $order->shipping_info['id'];
        $glovo = new Glovo(env('GLOVO_API'),env('GLOVO_API_SECRET'),'production');
        $position = $glovo->trackingOrder($order);
        $courier = $glovo->courierInfo($order);
        $tracking = array(
            'position'   => 
                array(
                    'lat'   => json_decode($position)->lat,
                    'lng'   => json_decode($position)->lon,
                ),
            'courier'   => 
                array(
                    'name'   => json_decode($courier)->courierName,
                    'phone'  => json_decode($courier)->phone,
                ),
        );
        return $tracking;
    }
    public function glovoCancel($id)
    { 
        $getOrder = Order::where('tracking_number',$id)->first();
        $order = $getOrder->shipping_info['id'];
        $glovo = new Glovo(env('GLOVO_API'),env('GLOVO_API_SECRET'),'production');
        if(empty($getOrder->shipping_info)){
            return array(
                "error" => 'Erro: O envio não existe.',
                "code" => "0065" 
            );
        }elseif(json_decode($glovo->getOrder($getOrder->shipping_info['id']))->state == "SCHEDULED" ){
            $glovo->cancelOrder($order);
            $getOrder->shipping_info = NULL;
            $getOrder->save();
            return array(
                "error" => 'Envio Cancelado com Sucesso',
                "code" => "0066" 
            );
        }else{
            return array(
                "error" => 'Este Envio não pode ser Cancelado',
                "code" => "0067" 
            ); 
        }
    }

    public function sendPush($order,$courier)
    { 
        if($order = Order::where('tracking_number',$order)->first()){
            if($courier = Courier::where('id',$courier)->first()){
                $sms = new Twilio(env('TWILIO_ACCOUNT'),env('TWILIO_TOKEN'),env('TWILIO_MSID'));
                $items = "";
                foreach($order->products as $product){
                    $items .= $product->pivot->order_quantity."x ".$product->name."\n";
                };
                $sms->sendSMS("+351".$courier->phone,"Entrega de ".env('APP_NAME')." \n---------------------\nCódigo do Pedido: ".strtoupper($order->tracking_number)."\n---------------------\nCliente: ".User::where('id',$order->customer_id)->first()->name."\nTel: ".$order->customer_contact."\n---------------------\nMorada: ".$order->billing_address['street_address']." ".$order->billing_address['state']." ".$order->billing_address['city']."\nInstruções: ".$order->billing_address['instructions']."\nMapa: https://www.google.com/maps/dir//".urlencode($order->billing_address['street_address']." ".$order->billing_address['city'])."\n---------------------\n".$items."---------------------\n\nEstafeta: ".$courier->name."\nEnviado em: ".date('H:i d/m/Y'))."\n\n";
                return array(
                    "success" => 'SMS Enviada com Sucesso',
                    "code" => "0066" 
                );
            }else{
                return array(
                    "error" => 'Erro: O estafeta não existe.',
                    "code" => "0065" 
                );
            }
        }else{
            return array(
                "error" => 'Erro: O pedido não existe.',
                "code" => "0065" 
            );
        }
    }
}
