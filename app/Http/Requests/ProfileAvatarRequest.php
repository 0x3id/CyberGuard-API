<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidationContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProfileAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'The avatar field is required.',
            'avatar.image'    => 'The avatar must be an image.',
            'avatar.mimes'    => 'The avatar must be a file of type: jpeg, png, jpg, webp.',
            'avatar.max'      => 'The avatar may not be greater than 2MB.',
            'avatar.uploaded' => 'The avatar could not be uploaded. The file may exceed the maximum allowed size of 2MB.',
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
}
