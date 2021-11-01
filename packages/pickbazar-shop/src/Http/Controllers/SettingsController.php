<?php

namespace PickBazar\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PickBazar\Database\Models\Address;
use PickBazar\Database\Models\Order;
use PickBazar\Database\Repositories\SettingsRepository;
use PickBazar\Http\Requests\SettingsRequest;
use PickBazar\Http\Controllers\ApiController;

use Prettus\Validator\Exceptions\ValidatorException;

class SettingsController extends CoreController
{
    public $repository;

    public function __construct(SettingsRepository $repository)
    {
        $this->repository = $repository;
    }


    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Address[]
     */
    public function index(Request $request)
    {
        return $this->repository->first();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param SettingsRequest $request
     * @return mixed
     * @throws ValidatorException
     */
    public function store(SettingsRequest $request)
    {
        
        $settings = $this->repository->first();
        if (isset($settings->id)) {
            $options['options'] = $request->options;

            if(isset($request->options['site']['address'])){
                $geo = json_decode(@file_get_contents('https://www.mapquestapi.com/geocoding/v1/address?key=z4oUxuZk2DKCB3VqPZjrN3e9YbjSIGTe&location='.urlencode($request->options['site']['address'])), true)['results'][0]['locations'][0]['displayLatLng'];
                
                $options['options']['site']['address_lat'] = $geo['lat'];
                $options['options']['site']['address_lng'] = $geo['lng'];
            }
            return $this->repository->update($options, $settings->id);
        } else {
            
            return $this->repository->create(['options' => $request['options']]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return JsonResponse
     */
    public function show($id)
    {
        try {
            return $this->repository->first();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong!'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param SettingsRequest $request
     * @param int $id
     * @return JsonResponse
     * @throws ValidatorException
     */
    public function update(SettingsRequest $request, $id)
    {
        $settings = $this->repository->first();
        if (isset($settings->id)) {
            return $this->repository->update($request->only(['options']), $settings->id);
        } else {
            return $this->repository->create(['options' => $request['options']]);
        }
    }

    public function posZS(Request $request)
    {
        $options['options'] = $this->repository->first()->options;
        $options['options']['api']['pos'] = $request->status;

        // Sync Waiting Orders
        if($request->status == "online"){
            $api = new ApiController();

            foreach(Order::where('sync_response','waiting_pos')->get() as $order){
                
                $sync_order = $api->sendOrderZS($api->orderZS($order->tracking_number));

                if(json_decode($sync_order,true)['Response']['StatusMessage'] == "Unauthorized"){
                    $sync_response = "waiting_pos";
                }else{
                    $sync_response = "order_created";
                }
                // Updating Order
                $update = Order::where('id',$order->id)->first();
                $update->sync_response = $sync_response;
                $update->save();
            }
        }

        $this->repository->update($options, 1);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return array
     */
    public function destroy($id)
    {
        return [
            'message' => 'Action not valid',
            'success' => true
        ];
    }
}
