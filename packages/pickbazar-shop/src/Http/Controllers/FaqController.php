<?php

namespace PickBazar\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PickBazar\Database\Models\Faq;
use PickBazar\Database\Repositories\FaqRepository;
use PickBazar\Http\Requests\FaqRequest;
use Prettus\Validator\Exceptions\ValidatorException;
use Illuminate\Support\Facades\Http;

class FaqController extends CoreController
{
    public $repository;

    public function __construct(FaqRepository $repository)
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
        return $this->repository->all();
    }



    /**
     * Store a newly created resource in storage.
     *
     * @param TypeRequest $request
     * @return mixed
     * @throws ValidatorException
     */
    public function store(FaqRequest $request)
    {
        $validatedData = $request->validated();
        return $this->repository->create($validatedData);
    }

    /**
     * Display the specified resource.
     *
     * @param $slug
     * @return JsonResponse
     */
    public function show($slug)
    {
        try {
            return $this->repository->findOneByFieldOrFail('id', $slug);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Faq Type not found!'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param TypeRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(FaqRequest $request, $id)
    {
        try {
            $validatedData = $request->validated();
            return $this->repository->findOrFail($id)->update($validatedData);
        } catch (\Exception $e) {
            
            return response()->json("['message' => 'Faq not found!'], 404");
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
            return response()->json(['message' => 'Faq not found!'], 404);
        }
    }
}
