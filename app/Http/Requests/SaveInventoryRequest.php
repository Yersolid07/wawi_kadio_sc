<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveInventoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'unit' => 'required|string|max:50',
            'current_stock' => $this->isMethod('post') ? 'required|numeric|min:0' : 'nullable',
            'initial_total_cost' => $this->isMethod('post') ? 'nullable|numeric|min:0' : 'nullable',
            'minimum_stock' => 'required|numeric|min:0',
            'price_per_unit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
