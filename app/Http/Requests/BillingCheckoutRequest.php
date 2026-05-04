<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BillingCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => 'required|string|in:starter,pro',
            'billing_data' => 'required|array',
            'billing_data.first_name' => 'required|string|max:255',
            'billing_data.last_name' => 'required|string|max:255',
            'billing_data.email' => 'required|email|max:255',
            'billing_data.phone_number' => 'required|string|max:32',
            'billing_data.city' => 'required|string|max:255',
            'billing_data.country' => 'required|string|size:2',
            'billing_data.street' => 'nullable|string|max:255',
            'billing_data.building' => 'nullable|string|max:64',
            'billing_data.floor' => 'nullable|string|max:32',
            'billing_data.apartment' => 'nullable|string|max:32',
            'billing_data.postal_code' => 'nullable|string|max:32',
        ];
    }

    protected function failedValidation(ValidationContract $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('plan') && is_string($this->input('plan'))) {
            $this->merge(['plan' => trim($this->input('plan'))]);
        }

        $bd = $this->input('billing_data');
        if (is_array($bd)) {
            $clean = [];
            foreach ($bd as $key => $value) {
                $clean[$key] = is_string($value) ? trim($value) : $value;
            }
            $this->merge(['billing_data' => $clean]);
        }
    }
}
