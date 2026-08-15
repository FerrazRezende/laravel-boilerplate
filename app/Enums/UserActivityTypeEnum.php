<?php

declare(strict_types=1);

namespace App\Enums;

enum UserActivityTypeEnum: string
{
    case STATUS_CHANGED = 'status_changed';
    case PAGE_VIEW = 'page_view';
    case DATABASE_CREATED = 'database_created';
    case CREDENTIAL_CREATED = 'credential_created';

    public function label(): string
    {
        return match ($this) {
            self::STATUS_CHANGED => __('Status Changed'),
            self::PAGE_VIEW => __('Page View'),
            self::DATABASE_CREATED => __('Database Created'),
            self::CREDENTIAL_CREATED => __('Credential Created'),
        };
    }
}
