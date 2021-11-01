<?php

namespace PickBazar\Http\Controllers;

use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PickBazar\Database\Models\Order;
use PickBazar\Database\Models\Settings;
use PickBazar\Database\Models\User;
use PickBazar\Database\Repositories\OrderRepository;
use PickBazar\Events\OrderCreated;
use PickBazar\Http\Requests\OrderCreateRequest;
use PickBazar\Http\Requests\OrderUpdateRequest;


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
}
