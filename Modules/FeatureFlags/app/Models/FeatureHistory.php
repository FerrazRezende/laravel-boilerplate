<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\FeatureFlags\Enums\FeatureHistoryActionEnum;
use Modules\Identity\Models\User;

class FeatureHistory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'feature_setting_id',
        'action',
        'actor_id',
        'previous_state',
        'new_state',
    ];

    protected function casts(): array
    {
        return [
            'action' => FeatureHistoryActionEnum::class,
            'previous_state' => 'array',
            'new_state' => 'array',
        ];
    }

    public function featureSetting(): BelongsTo
    {
        return $this->belongsTo(FeatureSetting::class, 'feature_setting_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
