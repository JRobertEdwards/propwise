<?php

namespace App\Http\Requests;

use App\Rules\ValidUkPostcode;
use Illuminate\Foundation\Http\FormRequest;

class PostcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'postcode' => ['required', 'string', 'max:8', new ValidUkPostcode()],
        ];
    }
}
