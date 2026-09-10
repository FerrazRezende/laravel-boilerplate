<?php

declare(strict_types=1);

namespace Modules\FeatureFlags\Models;

use App\Traits\HasKsuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\FeatureFlags\Enums\RolloutStrategyEnum;

class FeatureSetting extends Model
{
    use HasFactory, HasKsuid;

    protected $fillable = [
        'feature_name',
        'strategy',
        'percentage',
        'user_ids',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'strategy' => RolloutStrategyEnum::class,
            'percentage' => 'integer',
            'user_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function histories(): HasMany
    {
        return $this->hasMany(FeatureHistory::class, 'feature_setting_id');
    }

    public function scopeOfFeature($query, string $featureName)
    {
        return $query->where('feature_name', $featureName);
    }
}
