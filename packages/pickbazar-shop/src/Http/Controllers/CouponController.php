<?php

namespace PickBazar\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PickBazar\Http\Requests\CouponRequest;
use PickBazar\Http\Requests\UpdateCouponRequest;
use PickBazar\Database\Repositories\CouponRepository;
use Prettus\Validator\Exceptions\ValidatorException;

class CouponController extends CoreController
{
    public $repository;

    public function __construct(CouponRepository $repository)
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
        $limit = $request->limit ?   $request->limit : 15;
        return $this->repository->paginate($limit);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CouponRequest $request
     * @return LengthAwarePaginator|Collection|mixed
     * @throws ValidatorException
     */
    public function store(CouponRequest $request)
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
            return response()->json(['message' => 'Sorry! coupon not found.'], 404);
        }
    }
    /**
     * Verify Coupon by code.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code'     => 'required|string',
        ]);
        $code = $request->code;
        try {
            return $this->repository->verifyCoupon($code);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Sorry! Something went wrong.'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param CouponRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateCouponRequest $request, $id)
    {
        try {
            $this->repository->findOrFail($id);
            return $this->repository->update($request->validated(), $id);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Sorry! coupon ID does not exist.'], 404);
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
            return response()->json(['message' => 'Sorry! coupon ID does not exist.'], 404);
        }
    }
}
