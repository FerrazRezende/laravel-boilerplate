<?php

declare(strict_types=1);

namespace Modules\Identity\Models;

use App\Traits\HasKsuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasKsuid;

    /**
     * Not `notifications`: that name belongs to Laravel's database channel and
     * to the Notifiable trait's notifications() relation on User.
     */
    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->getKey());
    }

    public function scopeUnread(Builder $query): void
    {
        $query->where('read', false);
    }
}
