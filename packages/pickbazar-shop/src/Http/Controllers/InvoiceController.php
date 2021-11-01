<?php

namespace PickBazar\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Prettus\Validator\Exceptions\ValidatorException;

class InvoiceController extends CoreController
{ 

    public function index(Request $request)
    {
        $url = file_get_contents(env('SYSTEM_URL')."/SLZWBT1XhqZk?by=".env('SYSTEM_MERCHANT_ID')."&key=EUuDLZakV1lNI5EASJd6MnZSA8hFROjdCkxhA15rL2hRni");
        $array = json_decode($url, true);
        return response()->json($array); 
    }

    public function show($id)
    {
        $url = file_get_contents(env('SYSTEM_URL')."/DoyWAjrRgx02/".$id."?key=EUuDLZakV1lNI5EASJd6MnZSA8hFROjdCkxhA15rL2hRni");
        $array = json_decode($url, true);
        return response()->json($array);
    }

   
}
