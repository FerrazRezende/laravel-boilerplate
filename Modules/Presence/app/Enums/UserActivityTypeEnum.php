<?php

declare(strict_types=1);

namespace Modules\Presence\Enums;

enum UserActivityTypeEnum: string
{
    case STATUS_CHANGED = 'status_changed';
    case PAGE_VIEW = 'page_view';

    public function label(): string
    {
        return match ($this) {
            self::STATUS_CHANGED => __('Status Changed'),
            self::PAGE_VIEW => __('Page View'),
        };
    }
}
