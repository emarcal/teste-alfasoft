<?php

namespace PickBazar\Http\Controllers;

use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PickBazar\Database\Models\Order;
use PickBazar\Database\Models\OrderPending;
use PickBazar\Database\Models\Settings;
use PickBazar\Database\Models\Shipping;
use PickBazar\Database\Models\User;
use PickBazar\Database\Models\Profile;
use PickBazar\Database\Models\Twilio;
use PickBazar\Database\Repositories\OrderRepository;
use PickBazar\Events\OrderCreated;
use PickBazar\Http\Requests\OrderCreateRequest;
use PickBazar\Http\Requests\OrderUpdateRequest;
use Illuminate\Support\Facades\Http;
use PickBazar\Notifications\OrderPlacedSuccessfully;
use PickBazar\Notifications\SalePlacedSuccessfully;
use PickBazar\Database\Models\Product;
use PickBazar\Database\Models\Category;
use PickBazar\Http\Controllers\ApiController;

class OrderPendingController extends CoreController
{
    public $repository;

    public function __construct(OrderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Order[]
     */
    public function index()
    {

        if($_GET['_jx'] == "QC2E1k0BAuv1m4TJXZyuAeAHHsJZf7MO63LN2EnoK"){
            $order = OrderPending::where('tracking_number',$_GET['order'])->where('status',100)->first();
            if($order){
                $order->status = 1;
                $order->save();
                $this->createPayment($order);
                return response()->json(['message' => 'Success!'], 200);
            }else{
                return response()->json(['message' => 'Sorry! order not found.'], 404);
            }
           
        }
    }
    public function slugify($text, string $divider = '-')
    {
      $text = preg_replace('~[^\pL\d]+~u', $divider, $text);
      $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
      $text = preg_replace('~[^-\w]+~', '', $text);
      $text = trim($text, $divider);
      $text = preg_replace('~-+~', $divider, $text);
      $text = strtolower($text);
    
      if (empty($text)) {
        return 'n-a';
      }
    
      return $text;
    }

    
    public function status()
    {
        $order = OrderPending::where('tracking_number',$_GET['order'])->first();
        if($order){
            return response()->json($order->status);
        }else{
            return response()->json(['message' => 'Sorry! order not found.'], 404);
        }     
    }

    protected function createPayment(ApiController $api, $order)
    {
        // Notify By E-mail
        $customer = User::where('id',$order->customer_id)->first();
        $customer->notify(new OrderPlacedSuccessfully($order));

        // Notify Admin
        $notification = Settings::find(1)->options['notification'];
        if($notification['status'] == "on" && $notification['email']){
            $customer->email = $notification['email'];
            $customer->notify(new SalePlacedSuccessfully($order));
        }
        $profile = Profile::where('customer_id',$order->customer_id)->first();
        $this->sendSMS($order->tracking_number, $profile->contact);

        if($order->discount != 0){
            $discount = $order->discount;
        }else{
            $discount = 0;
        }

        if($order->payment_gateway == "mbway"){
            $service = ($order->paid_total * 0.07) + 0.07;
        }elseif($order->payment_gateway == "mb"){
            $service = ($order->paid_total * 0.017) + 0.22;
        }else{
            $service = 0;
        }

        // Sync Order with ZONE SOFT
        // Check POS
        if(isset(Settings::where('id',1)->first()['options']['api'])){
            if(Settings::where('id',1)->first()['options']['api']['pos'] == "offline"){
                $sync_response = "waiting_pos";
            }else{
                $sync_order = $api->sendOrderZS($api->orderZS($order->tracking_number));
                if(json_decode($sync_order,true)['Response']['StatusMessage'] == "Unauthorized"){
                    $sync_response = "waiting_pos";
                }else{
                    $sync_response = "order_created";
                }
            }
            // Saving Response
            $update = Order::where('tracking_number',$order->tracking_number)->first();
            $update->sync_response = $sync_response;
            $update->save();
        }

        // Send to SRV
        $response = Http::get(env('SYSTEM_URL').'/QPRc7cGSVxHZ', [
            'merchant' => env('SYSTEM_MERCHANT'),
            'value' => ($order->amount + $order->sales_tax) - $order->discount,
            'fee' => $order->sales_tax,
            'delivery' => $order->delivery_fee,
            'discount' => $discount,
            'service' => $service,
            'order' => $order->tracking_number,
            'customer_id' => $order->customer_id,
            'customer_email' => User::where('id',$order->customer_id)->first()->email,
            'customer_phone' => $order->customer_contact,
            'payment_method'=>$order->payment_gateway,
            'key'=>'pThT7atLQIBKlOZQh43qmVV1bCDEN4TPiVFv',
        ]);
    }

    public function logo()
    {

        if($logo = Settings::where('id',1)->first()->options){
            return $logo;
        }
    }

    
    protected function sendSMS($order,$number)
    {
        $sms = new Twilio(env('TWILIO_ACCOUNT'),env('TWILIO_TOKEN'),env('TWILIO_MSID'));
        $sms->sendSMS('+351'.$number,env('APP_NAME').' - Seu pedido foi feito com sucesso. ID de rastreamento de pedido: '.$order.'. Consulte o seu pedido em '.env('SHOP_URL').'order-received/'.$order.'.');
        
        // Notify Admin
        $notification = Settings::find(1)->options['notification'];
        if($notification['status'] == "on" && $notification['phone']){
            $sms->sendSMS('+351'.$notification['phone'],env('APP_NAME').' - Venda Realizada. ID de rastreamento de pedido: '.$order.'. Consulte o esta venda em '.env('ADMIN_URL').'orders/details/'.$order.'.');
        }

       
    }
}
