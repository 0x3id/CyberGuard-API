<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'sometimes|string|max:255',
            'job_title' => 'sometimes|nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.string'   => 'The full name must be a string.',
            'full_name.max'      => 'The full name may not be greater than 255 characters.',
            'job_title.string'   => 'The job title must be a string.',
            'job_title.max'      => 'The job title may not be greater than 255 characters.',
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
        $cleaned = [];

        foreach ($this->all() as $key => $value) {
            if (is_string($value)) {
                $cleaned[$key] = trim($value);
            } else {
                $cleaned[$key] = $value;
            }
        }

        $this->merge($cleaned);
    }
}
