<?php

declare(strict_types=1);

namespace Modules\Presence\Models;

use App\Traits\HasKsuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Identity\Models\User;
use Modules\Presence\Enums\UserActivityTypeEnum;
use Modules\Presence\Enums\UserStatusEnum;

class UserActivity extends Model
{
    use HasFactory, HasKsuid, MassPrunable;

    /**
     * A page view is logged up to once a minute per signed-in user, so the
     * table grows without bound unless something trims it. Pruned daily by
     * the schedule in routes/console.php.
     */
    public const RETENTION_DAYS = 90;

    protected $fillable = [
        'user_id',
        'activity_type',
        'from_status',
        'to_status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => UserActivityTypeEnum::class,
            'from_status' => UserStatusEnum::class,
            'to_status' => UserStatusEnum::class,
            'metadata' => 'array',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeStatusChanged($query)
    {
        return $query->where('activity_type', UserActivityTypeEnum::STATUS_CHANGED);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
