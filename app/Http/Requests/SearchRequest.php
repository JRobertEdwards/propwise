<?php

namespace App\Http\Requests;

use App\Rules\ValidUkPostcode;
use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'postcode'        => ['required', 'string', 'max:8', new ValidUkPostcode()],
            'radius'          => ['sometimes', 'numeric', 'in:0.5,1,2'],
            'house_number'    => ['sometimes', 'string', 'max:50'],
            'property_type'   => ['sometimes', 'array'],
            'property_type.*' => ['in:D,S,T,F,O'],
            'date_from'       => ['sometimes', 'date', 'before_or_equal:date_to'],
            'date_to'         => ['sometimes', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function radius(): float
    {
        return (float) $this->input('radius', 1.0);
    }
}
