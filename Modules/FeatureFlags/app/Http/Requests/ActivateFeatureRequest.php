<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\FeatureFlags\Enums\RolloutStrategyEnum;

class ActivateFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy' => ['required', Rule::enum(RolloutStrategyEnum::class)],
            'percentage' => ['nullable', 'integer', 'min:0', 'max:100', 'required_if:strategy,percentage'],
            'user_ids' => ['nullable', 'array', 'required_if:strategy,users'],
            'user_ids.*' => ['string'],
        ];
    }
}
