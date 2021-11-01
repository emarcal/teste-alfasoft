<?php


namespace PickBazar\Database\Repositories;

use Ignited\LaravelOmnipay\Facades\OmnipayFacade as Omnipay;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PickBazar\Database\Models\Mbway;
use PickBazar\Database\Models\Paypal;
use PickBazar\Database\Models\Mb;
use PickBazar\Database\Models\Coupon;
use PickBazar\Database\Models\Order;
use PickBazar\Database\Models\OrderPending;
use PickBazar\Database\Models\Settings;
use PickBazar\Events\OrderCreated;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Exceptions\ValidatorException;
use Illuminate\Support\Facades\Http;
use PickBazar\Database\Models\User;
use PickBazar\Database\Models\Twilio;
use PickBazar\Database\Models\Profile;
use PickBazar\Notifications\OrderPlacedSuccessfully;
use PickBazar\Notifications\SalePlacedSuccessfully;
use PickBazar\Http\Controllers\ApiController;

class OrderRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'tracking_number' => 'like',
    ];
    /**
     * @var string[]
     */
    protected $dataArray = [
        'tracking_number',
        'customer_id',
        'customer_nif',
        'status',
        'amount',
        'sales_tax',
        'paid_total',
        'total',
        'delivery_time',
        'payment_gateway',
        'discount',
        'coupon_id',
        'payment_id',
        'logistics_provider',
        'billing_address',
        'shipping_address',
        'delivery_fee',
        'customer_contact'
    ];

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
        }
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Order::class;
    }

    /**
     * @param $request
     * @return LengthAwarePaginator|JsonResponse|Collection|mixed
     */
    public function storeOrder($request)
    {
        // Calculating Delivery Time
        if(isset(Settings::find(1)->options['site']['delivery_time'])){
            $delivery_time = Settings::find(1)->options['site']['delivery_time'];
        }else{
            $delivery_time = 0;
        }
        

        if(Settings::find(1)->options['scheduleType'] == "store"){
            $date = date("Y-m-d");
            $request['delivery_time'] = $this->chooseDay($date);
        }elseif(Settings::find(1)->options['scheduleType'] == "client-by"){
            $request['delivery_time'] = $this->chooseDay($request['delivery_time']);
        }else{
            $request['delivery_time'] = $this->chooseDay($request['delivery_time']);
        }

        $site = Settings::find(1)->options['site'];
        
        $address = array(
            'id' => 'PICKUP',
            'street_address' => $site['address'],
            'state' => $site['address_details'],
            'instructions' => $site['address_instructions'],
            'lat' => $site['address_lat'],
            'lng' => $site['address_lng'],
        );
        
        $request['shipping_address'] = $address;
        
        $store = base_convert(env('SYSTEM_MERCHANT_ID'), 10, 36);
        $request['tracking_number'] = Str::random(6).$store;
        $request['customer_id'] = $request->user()->id;
        $request['customer_email'] = $request->user()->email;

        $discount = $this->calculateDiscount($request);
        if ($discount) {
            $request['paid_total'] = $request['amount'] + $request['sales_tax'] + $request['delivery_fee'] - $discount;
            $request['total'] = $request['amount'] + $request['sales_tax'] + $request['delivery_fee'] - $discount;
            $request['discount'] =  $discount;
        } else {
            $request['paid_total'] = $request['amount'] + $request['sales_tax'] + $request['delivery_fee'];
            $request['total'] = $request['amount'] + $request['sales_tax'] + $request['delivery_fee'];
        }
        $payment_gateway = $request['payment_gateway'];
        switch ($payment_gateway) {
            case 'cod':
                // Cash on Delivery no need to capture payment
                return $this->createOrder($request);
                break;
            case 'mbway':
                // MB WAY
                $client = new Mbway(env('MBWAY_API_KEY'));
                $result =  $client->callIfthenpayMbWayAPI(env('MB_ENT'),$request['tracking_number'],''.$request['customer_email'].'',$request['contact_mbway'],$request['paid_total']);
                $request['status'] = 100;
                return $this->createOrder($request);
                break;
            case 'mb':
                // MULTIBANCO
                $client = new Mb(env('MBWAY_API_KEY'));
                $request['status'] = 100;
                $order = $this->createOrder($request);
                $result =  $client->create_mb_reference($order['id'],$request['tracking_number'],$request['paid_total']);
                
                $result['id'] = $order['id'];
                return $result;
                break;
                // PAYPAL
            case 'paypal':
                
                break;
        }
        // STRIPE
        $response = $this->capturePayment($request);

        if ($response['status'] == "succeeded") {
            $payment_id = $response['id'];
            $request['payment_id'] = $payment_id;
            $this->createPayment($request);
            $created = $this->createOrder($request);
            $order = OrderPending::where('id',$created->id)->first();
            $customer = User::where('id',$order->customer_id)->first();
            $profile = Profile::where('customer_id',$order->customer_id)->first();


            $this->sendSMS($order->tracking_number, $profile->contact);

            $customer->notify(new OrderPlacedSuccessfully($order));
           
            // Notify Admin
            $notification = Settings::find(1)->options['notification'];
            if($notification['status'] == "on" && $notification['email']){
                $customer->email = $notification['email'];
                $customer->notify(new SalePlacedSuccessfully($order));
            }

            return $created;
            
            
            

        } elseif ($response->isRedirect()) {
            return $response->getRedirectResponse();
        } else {
            return ['message' => 'Payment not Successful!', 'code' => 404, 'success' => false];
        }
    }



    /**
     * @param $request
     * @return mixed
     */
    protected function sendSMS($order,$number)
    {
        $sms = new Twilio(env('TWILIO_ACCOUNT'),env('TWILIO_TOKEN'),env('TWILIO_MSID'));
        $sms->sendSMS('+351'.$number,env('APP_NAME').' - Seu pedido foi feito com sucesso. ID de rastreamento de pedido: '.$order.'. Consulte o seu pedido em '.env('SHOP_URL').'order-received/'.$order.'.');
        $notification = Settings::find(1)->options['notification'];
        if($notification['status'] == "on" && $notification['phone']){
            $sms->sendSMS('+351'.$notification['phone'],env('APP_NAME').' - Venda Realizada. ID de rastreamento de pedido: '.$order.'. Consulte o esta venda em '.env('ADMIN_URL').'orders/details/'.$order.'.');
        }
    }

    protected function capturePayment($request)
    {
        $key = env('STRIPE_API_KEY');
      
        $stripe = new \Stripe\StripeClient($key);
        $token = $stripe->tokens->create([
            'card' => $request['card'],
        ]);
        \Stripe\Stripe::setApiKey($key);    
        $customer = \Stripe\Customer::create(array(
            'email' => $request['customer_email'],
            'source'  => $token
        ));   
        
        $charge = $stripe->charges->create([
            'amount' => (number_format($request['paid_total'], 2, '.', '')) * 100, 
            'currency' => 'eur',
            'customer' => $customer->id,
            'description' => 'stripe_payment',
        ]);



        return $charge;
    }

    /**
     * @param $request
     * @return array|LengthAwarePaginator|Collection|mixed
     */
    protected function createOrder($request)
    {
        try {
            $orderInput = $request->only($this->dataArray);
            $products = $this->processProducts($request['products']);
            $order = $this->create($orderInput);
            $order->products()->attach($products);
            event(new OrderCreated($order));

            // Sync Order with ZONE SOFT
            $api = new ApiController();
            if($order['status'] != 100){
                // Check POS

                if(isset(Settings::where('id',1)->first()['options']['api'])){
                    if(Settings::where('id',1)->first()['options']['api']['pos'] == "offline"){
                        $sync_response = "waiting_pos";
                    }else{
                        $sync_order = $api->sendOrderZS($api->orderZS($order['tracking_number']));
                        if(json_decode($sync_order,true)['Response']['StatusMessage'] == "Unauthorized"){
                            $sync_response = "waiting_pos";
                        }else{
                            $sync_response = "order_created";
                        }
                    }
                    // Saving Response
                    $update = Order::where('tracking_number',$request['tracking_number'])->first();
                    $update->sync_response = $sync_response;
                    $update->save();
                }
                
            }
            


            return $order;
        } catch (ValidatorException $e) {
            return ['message' => 'Something went wrong!', 'code' => 500, 'error' => true];
        }
    }
  

    protected function processProducts($products)
    {
        foreach ($products as $key => $product) {
            if (!isset($product['variation_option_id'])) {
                $product['variation_option_id'] = null;
                $products[$key] = $product;
            }
        }
        return $products;
    }

    protected function calculateDiscount($request)
    {
        try {
            if (!isset($request['coupon_id'])) {
                return false;
            }
            $coupon = Coupon::findOrFail($request['coupon_id']);
            if (!$coupon->is_valid) {
                return false;
            }
            switch ($coupon->type) {
                case 'percentage':
                    return ($request['amount'] * $coupon->amount) / 100;
                case 'fixed':
                    return $coupon->amount;
                    break;
                case 'free_shipping':
                    return isset($request['delivery_fee']) ? $request['delivery_fee'] : false;
                    break;
            }
            return false;
        } catch (\Exception $exception) {
            return false;
        }
    }

    protected function chooseDay($date)
    {
        $delivery_time = 0;
        if(isset(Settings::find(1)->options['site']['delivery_time'])){
            $delivery_time = Settings::find(1)->options['site']['delivery_time'];
        }
        // 
        $i = 0;
        $choosed = 0;
        while ($choosed != 1){
            if(Settings::find(1)->options['scheduleType'] == "client"){
                $delivery_time = 0;
            }
            $current = date('d-m-Y', strtotime($date. ' + '.$delivery_time.' days'));

            $week = date('w', strtotime($current));
            
            if($week == 0){
                $current_week = Settings::find(1)->options['schedule']['sunday'];
            }elseif($week == 1){
                $current_week = Settings::find(1)->options['schedule']['monday'];
            }elseif($week == 2){
                $current_week = Settings::find(1)->options['schedule']['tuesday'];
            }elseif($week == 3){
                $current_week = Settings::find(1)->options['schedule']['wednesday'];
            }elseif($week == 4){
                $current_week = Settings::find(1)->options['schedule']['thursday'];
            }elseif($week == 5){
                $current_week = Settings::find(1)->options['schedule']['friday'];
            }elseif($week == 6){
                $current_week = Settings::find(1)->options['schedule']['saturday'];
            } 
            if($current_week == true){
                return $choosed  = date('d-m-Y', strtotime($date. ' + '.$delivery_time.' days'));
                $choosed = 1;
            }else{
                $delivery_time++;
            }
        }
        if(!$choosed){
            $choosed = $date;
        }
        return $choosed;
    }
    
    protected function createPayment($request)
    {
       

        if($request['discount'] != 0){
            $discount = $request['discount'];
        }else{
            $discount = 0;
        }
        
        if($request['payment_gateway'] == "stripe"){
            $service = ($request['paid_total'] * 0.014) + 0.25;
        }else{ 
            $service = 0;
        }
        


        // Send To SRV
        return $response = Http::get(env('SYSTEM_URL').'/QPRc7cGSVxHZ', [
            'merchant' => env('SYSTEM_MERCHANT_ID'),
            'value' => ($request['amount'] + $request['sales_tax']) - $request['discount'],
            'fee' => $request['sales_tax'],
            'service' => $service,
            'delivery' => $request['delivery_fee'],
            'discount' => $discount,
            'order' => $request['tracking_number'],
            'customer_id' => $request['customer_id'],
            'customer_nif' => $request['customer_nif'],
            'customer_email' => $request['customer_email'],
            'customer_phone' => $request['customer_contact'],
            'payment_method'=>$request['payment_gateway'],
            'key'=>'pThT7atLQIBKlOZQh43qmVV1bCDEN4TPiVFv',

        ]);
    }
}
