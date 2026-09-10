<?php

declare(strict_types=1);

namespace Modules\Presence\Models;

use App\Traits\HasKsuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Presence\Enums\UserActivityTypeEnum;
use Modules\Presence\Enums\UserStatusEnum;

class UserActivity extends Model
{
    use HasFactory, HasKsuid;

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
