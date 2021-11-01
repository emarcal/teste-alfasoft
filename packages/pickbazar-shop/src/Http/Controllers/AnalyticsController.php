<?php

namespace PickBazar\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PickBazar\Database\Models\Invoice;
use PickBazar\Database\Models\Address;
use PickBazar\Database\Repositories\AddressRepository;
use PickBazar\Http\Requests\AddressRequest;
use Prettus\Validator\Exceptions\ValidatorException;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use PickBazar\Database\Models\Order;
use PickBazar\Database\Models\Product;
use PickBazar\Enums\Permission;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission as ModelsPermission;

class AnalyticsController extends CoreController
{
    public $repository;

    public function __construct(AddressRepository $repository)
    {
        $this->repository = $repository;
    }


    public function analytics(Request $request)
    {
        if ($request->user() && $request->user()->hasPermissionTo(Permission::SUPER_ADMIN)) {
            //$totalRevenue = DB::table('orders')->whereDate('created_at', '>', Carbon::now()->subDays(30))->sum('paid_total');
            //$totalOrders = DB::table('orders')->whereDate('created_at', '>', Carbon::now()->subDays(30))->count();
            //$todaysRevenue = DB::table('orders')->whereDate('created_at', '>', Carbon::now()->subDays(1))->sum('paid_total');
            $data = Http::get(env('SYSTEM_URL').'/mSmbA?by='.env('SYSTEM_MERCHANT_ID').'&key=V1lNI5E');

            $totalRevenue = json_decode($data)->value;
            $todaysRevenue = json_decode($data)->today;
            $totalOrders = json_decode($data)->sales;

            $customerPermission = ModelsPermission::where('name', Permission::CUSTOMER)->first();
            $newCustomers = $customerPermission->users()->whereDate('created_at', '>', Carbon::now()->subDays(30))->count();
            
            
             $totalYearSaleByMonth = json_decode($data)->year;

            /*$totalYearSaleByMonth =
                $orders = DB::table('orders')->selectRaw(
                    "sum(paid_total) as total, DATE_FORMAT(created_at,'%M') as month"
                )->whereYear('created_at', date('Y'))->groupBy('month')->get();
            */


            $months = array (
                0 => 
                array (
                  'total' => 0,
                  'month' => 'January',
                ),
                1 => 
                array (
                  'total' => 0,
                  'month' => 'February',
                ),
                2 => 
                array (
                  'total' => 0,
                  'month' => 'March',
                ),
                3 => 
                array (
                  'total' => 0,
                  'month' => 'April',
                ),
                4 => 
                array (
                  'total' => 0,
                  'month' => 'May',
                ),
                5 => 
                array (
                  'total' => 0,
                  'month' => 'June',
                ),
                6 => 
                array (
                  'total' => 0,
                  'month' => 'July',
                ),
                7 => 
                array (
                  'total' => 0,
                  'month' => 'August',
                ),
                8 => 
                array (
                  'total' => 0,
                  'month' => 'September',
                ),
                9 => 
                array (
                  'total' => 0,
                  'month' => 'October',
                ),
                10 => 
                array (
                  'total' => 0,
                  'month' => 'November',
                ),
                11 => 
                array (
                  'total' => 0,
                  'month' => 'December',
                ),
              );

              $processedData = array (
                0 => 
                array (
                  'total' => 0,
                  'month' => 'January',
                ),
                1 => 
                array (
                  'total' => 0,
                  'month' => 'February',
                ),
                2 => 
                array (
                  'total' => 0,
                  'month' => 'March',
                ),
                3 => 
                array (
                  'total' => 0,
                  'month' => 'April',
                ),
                4 => 
                array (
                  'total' => 0,
                  'month' => 'May',
                ),
                5 => 
                array (
                  'total' => 0,
                  'month' => 'June',
                ),
                6 => 
                array (
                  'total' => 0,
                  'month' => 'July',
                ),
                7 => 
                array (
                  'total' => 0,
                  'month' => 'August',
                ),
                8 => 
                array (
                  'total' => 0,
                  'month' => 'September',
                ),
                9 => 
                array (
                  'total' => 0,
                  'month' => 'October',
                ),
                10 => 
                array (
                  'total' => 0,
                  'month' => 'November',
                ),
                11 => 
                array (
                  'total' => 0,
                  'month' => 'December',
                ),
              );

            foreach ($months as  $key => $month){
                foreach($totalYearSaleByMonth as $value) {

                    if($value->month == $month['month']){
                        $processedData[$key]['total'] = $value->total;
                    }
                }
           
           
            }
            // // foreach ($months as $key => $month) {
            //     foreach ($totalYearSaleByMonth as $value) {
            //         if ($value->month === $month) {
            //             $processedData[$key] = $value;
            //         } else {
            //             $processedData[$key] = ['total' => 0, 'month' => $month];
            //         }
            //     }
            // }


            return [
                'totalRevenue' => $totalRevenue,
                'todaysRevenue' => $todaysRevenue,
                'totalOrders' => $totalOrders,
                'newCustomers' =>  $newCustomers,
                'totalYearSaleByMonth' => $processedData
            ];
        }
        throw ValidationException::withMessages([
            'error' => ['User is not logged in or doesn\'t have enough permission.'],
        ]);
    }

    public function popularProducts(Request $request)
    {
        $limit = $request->limit ? $request->limit : 10;
        $products = Product::withCount('orders')->orderBy('orders_count', 'desc')->limit($limit)->get();
        return $products;
    }
}
