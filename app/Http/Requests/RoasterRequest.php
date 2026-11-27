<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:100', 'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('roasters')->ignore($this->route('roaster'))], 'city' => 'required|string|max:100', 'story' => 'required|string|max:3000', 'color' => 'required|hex_color', 'shipping_policy' => 'required|string|max:200', 'shipping_cents' => 'required|integer|min:0|max:2000', 'max_roast_days' => 'required|integer|min:1|max:'.config('platform.max_roast_days')];
    }
}
