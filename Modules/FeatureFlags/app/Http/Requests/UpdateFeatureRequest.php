<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['string'],
        ];
    }
}
