<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:120', 'street' => 'required|string|max:200', 'postcode' => ['required', 'regex:/^[1-9][0-9]{3}\s?[A-Za-z]{2}$/'], 'city' => 'required|string|max:100', 'checkout_key' => 'required|uuid'];
    }
}
