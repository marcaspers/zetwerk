<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CoffeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return ['roaster_id' => 'required|exists:roasters,id', 'name' => 'required|string|max:100', 'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('coffees')->ignore($this->route('coffee'))], 'country' => 'required|string|max:100', 'region' => 'required|string|max:100', 'producer' => 'required|string|max:100', 'variety' => 'required|string|max:100', 'process' => 'required|in:Washed,Natural,Decaf,Honey', 'roast' => 'required|in:Licht,Medium-licht,Medium', 'method' => 'required|in:filter,espresso,allround', 'notes_text' => 'required|string|max:200', 'description' => 'required|string|max:3000', 'color' => 'required|hex_color', 'roast_to_order' => 'required|boolean', 'max_roast_days' => 'nullable|integer|min:1|max:'.config('platform.max_roast_days')];
    }
}
