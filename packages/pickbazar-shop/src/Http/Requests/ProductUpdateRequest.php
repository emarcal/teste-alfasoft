<?php

namespace PickBazar\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use PickBazar\Enums\ProductType;

class ProductUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name'        => ['string', 'max:255'],
            'price'       => ['nullable', 'numeric'],
            'sale_price'  => ['nullable', 'lte:price'],
            'type_id'     => ['exists:PickBazar\Database\Models\Type,id'],
            'categories'  => ['exists:PickBazar\Database\Models\Category,id'],
            'product_type' => [Rule::in([ProductType::SIMPLE, ProductType::VARIABLE])],
            'unit'        => ['string'],
            'description' => ['nullable', 'string'],
            'quantity'    => ['nullable', 'integer'],
            'sku'         => ['string'],
            'image'       => ['array'],
            'gallery'     => ['array'],
            'status'      => ['string', Rule::in(['publish', 'draft'])],
            'height'      => ['nullable', 'string'],
            'length'      => ['nullable', 'string'],
            'width'       => ['nullable', 'string'],
            'in_stock'    => ['boolean'],
            'is_taxable'  => ['boolean'],
        ];
    }

    public function failedValidation(Validator $validator)
    {
        // TODO: Need to check from the request if it's coming from GraphQL API or not .
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }
}
