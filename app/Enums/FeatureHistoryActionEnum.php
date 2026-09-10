<?php

declare(strict_types=1);

namespace App\Enums;

enum FeatureHistoryActionEnum: string
{
    case ACTIVATED = 'activated';
    case DEACTIVATED = 'deactivated';
    case UPDATED = 'updated';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVATED => __('Activated'),
            self::DEACTIVATED => __('Deactivated'),
            self::UPDATED => __('Updated'),
        };
    }
}
