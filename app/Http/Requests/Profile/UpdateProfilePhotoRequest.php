<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => __('The photo field is required.'),
            'photo.mimes' => __('The photo must be a file of type: jpg, jpeg, png.'),
            'photo.max' => __('The photo may not be greater than 2MB.'),
            'photo.dimensions' => __('The photo must be between 100x100 and 2000x2000 pixels.'),
        ];
    }
}
