<?php

namespace PickBazar\Http\Controllers;

use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PickBazar\Database\Models\Order;
use PickBazar\Database\Models\ZoneSoft;
use PickBazar\Database\Models\Settings;
use PickBazar\Database\Models\User;
use PickBazar\Database\Models\VariationOption;
use PickBazar\Database\Models\OrderStatus;
use PickBazar\Database\Repositories\OrderRepository;
use PickBazar\Events\OrderCreated;
use PickBazar\Http\Requests\OrderCreateRequest;
use PickBazar\Http\Requests\OrderUpdateRequest;
use Carbon\Carbon;


class OrderController extends CoreController
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
    public function index(Request $request)
    {
        $limit = $request->limit ?   $request->limit : 10;
        $user = $request->user();
        if ($user->can('super_admin')) {
            return $this->repository->paginate($limit);
        } else {
            return $this->repository->where('customer_id', '=', $user->id)->paginate($limit);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param OrderCreateRequest $request
     * @return LengthAwarePaginator|\Illuminate\Support\Collection|mixed
     */
    public function store(OrderCreateRequest $request)
    {
        return $this->repository->storeOrder($request);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        try {
            $order = $this->repository->with(['products', 'status'])->where('id',$id)->orWhere('tracking_number',$id)->first();
            if ($user->id === $order->customer_id || $user->can('super_admin')) {
                return $order;
            } else {
                return response()->json(['message' => 'Does not have proper permission'], 403);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Order not found!'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param OrderUpdateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(OrderUpdateRequest $request, $id)
    {
        try {
            $order = $this->repository->findOrFail($id);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Order not found!'], 404);
        }
        if (isset($request['products'])) {
            $order->products()->sync($request['products']);
        }
        $order->update($request->except('products'));
        return $order;
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
            return response()->json(['message' => 'Order not found!'], 404);
        }
    }


    public function directPrintList(Request $request)
    {
        if($request->key == "DP_02795b636417b0c0f830c8712f50b11787bd66cee5c6343776604b7fe209b28b"){
            $page = [];
            foreach(Order::whereNull('printed')->where('delivery_time','like',"%".date('d-m-Y')."%")->limit(4)->get() as $order){
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
                    if($product->pivot->variation_option_id){
                        $variation = VariationOption::where('id',$product->pivot->variation_option_id)->first()->title;
                    }else{
                        $variation = "NULL";
                    }
                    $products[] = array(
                        "name"               => $product->name,
                        "variation"          => $variation,
                        "unit"               => $product->unit,
                        "qty"                => $product->pivot->order_quantity,
                        "subtotal"           => number_format($product->pivot->subtotal,2),
                    );
                }
                $settings = Settings::where('id',1)->first()->options;
                $page[] = array(
                    "store"              => array(
                        "logo"         => $settings['site']['image']['thumbnail'],
                        "nif"        => $settings['site']['fiscal'],
                        "name"         => $settings['siteTitle'],
                        "email"        => $settings['site']['email'],
                        "contact"      => $settings['site']['phone'],
                        "color"        => $settings['site']['color'],
                        "logo_system"  => array(
                            "white"    => 'https://admin.lojadodia.com/storage/uploads/Ezeoz0Xd2AmVtHzITs4skA9rkVjfe4aF39yuKH4I.png',
                            "dark"     => 'https://admin.lojadodia.com/storage/uploads/grxHt9alS550kxZNxjxWt0I85j10MVeRUyJkaoCQ.png',
                        ),
                        "header"       => null,
                        "footer"       => "Processado por LOJA DO DIA",
                    ),
                    "reference"              => array(
                        "id"                 => $order->id,
                        "code"               => $order->tracking_number,
                    ),
                    "customer"               => array(
                        "id"                 => $user->id,
                        "name"               => $user->name,
                        "email"              => $user->email,
                        "phone"              => $order->customer_contact,
                        "nif"                => $order->customer_nif,
                        "address"            => array(
                            "street"         => $order->billing_address['street_address'],
                            "details"        => $order->billing_address['state'],
                            "zip"            => $order->billing_address['zip'],
                            "lat"            => @$order->billing_address['lat'],
                            "lng"            => @$order->billing_address['lng'],
                            "instructions"   => @$order->billing_address['instructions'],
                            "city"           => $order->billing_address['city']
                        ),
                    ),
                    "products"               => $products,
                    "amount"                 => array(
                        "value"              => number_format($order->amount,2),
                        "tax"                => number_format($order->sales_tax,2),
                        "delivery"           => number_format($order->delivery_fee,2),
                        "total"              => number_format($order->total,2)
                    ),
                    "info"                   => array(
                        "created_at"         => date("H:i d-m-Y",strtotime($order->created_at)),
                        "delivery_to"        => $delivery_hour.$order->delivery_time,
                        "payment_method"     => $order->payment_gateway,
                        "payment_status"     => 'paid',
                        "obs"                => $order->obs,
                        "type"               => 'delivery',
                        "status"             => OrderStatus::where('id',$order->status)->first()->name
                    ),

                
                );
            }
            return response()->json($page);
        }else{
            return response()->json(['message' => 'Invalid key!'], 404);
        }
            
    }
    public function directPrintPage(Request $request, $order)
    {
        if($request->key == "DP_1583dc3f669c8f10603f30c672d0bda9a8050bdd508c5f43bd1b4b35a9d0c787"){
            if($order = Order::where('tracking_number',$order)->first()){
                $order->printed = 1;
                $order->save();
                return response()->json(['message' => 'Order Printed'], 200);
            }else{
                return response()->json(['message' => 'Order not found!'], 404);
            }
        }else{
            return response()->json(['message' => 'Invalid key!'], 404);
        }
    }
    public function directPrintResurrect(Request $request, $order)
    {
        if($request->key == "DP_1583dc3f669c8f10603f30c672d0bda9a8050bdd508c5f43bd1b4b35a9d0c787"){
            if($order = Order::where('tracking_number',$order)->first()){
                $order->printed = NULL;
                $order->save();
                return response()->json(['message' => 'Order Ressurected'], 200);
            }else{
                return response()->json(['message' => 'Order not found!'], 404);
            }
        }else{
            return response()->json(['message' => 'Invalid key!'], 404);
        }
    }
    public function directPrintInfo(Request $request)
    {
        if($request->key == "DP_6a5a5a50cbc4e8858c6efa5af738020832b1d733ff252b0fbb520d70efb426c7"){
            $settings = Settings::where('id',1)->first()->options;
            $store = array(
                "logo"         => $settings['site']['image']['thumbnail'],
                "nif"        => $settings['site']['fiscal'],
                "name"         => $settings['siteTitle'],
                "email"        => $settings['site']['email'],
                "contact"      => $settings['site']['phone'],
                "color"        => $settings['site']['color'],
                "logo_system"  => array(
                    "white"    => 'https://admin.lojadodia.com/storage/uploads/Ezeoz0Xd2AmVtHzITs4skA9rkVjfe4aF39yuKH4I.png',
                    "dark"     => 'https://admin.lojadodia.com/storage/uploads/Ms1wr4yIjRwU87MmEBZVHjQeIMbG0QLOk0f4lyH6.png',
                ),
                "header"       => null,
                "footer"       => "Processado por LOJA DO DIA",
            );
            return response()->json($store);
        }else{
            return response()->json(['message' => 'Invalid key!'], 404);
        }
    }
}

