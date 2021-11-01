<?php

namespace PickBazar\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PickBazar\Database\Repositories\AttributeRepository;
use PickBazar\Http\Requests\AttributeRequest;
use Prettus\Validator\Exceptions\ValidatorException;

class AttributeController extends CoreController
{
    public $repository;

    public function __construct(AttributeRepository $repository)
    {
        $this->repository = $repository;
    }


    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Type[]
     */
    public function index(Request $request)
    {
        return $this->repository->with('values')->all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param AttributeRequest $request
     * @return mixed
     * @throws ValidatorException
     */
    public function store(AttributeRequest $request)
    {
        $validatedData = $request->all();
        return $this->repository->create($validatedData);
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
            return $this->repository->with('values')->findOrFail($id);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Attribute Type not found!'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param AttributeRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(AttributeRequest $request, $id)
    {
        try {
            $validatedData = $request->all();
            return $this->repository->findOrFail($id)->update($validatedData);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Attribute Type not found!'], 404);
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
            return response()->json(['message' => 'Attribute Type not found!'], 404);
        }
    }
}
