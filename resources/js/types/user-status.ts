/**
 * User Status System Types
 *
 * TypeScript types for the User Status & Presence system.
 * Matches the PHP models: UserStatusEnum, UserActivity, and related services.
 */

/**
 * User status values
 * Matches App\Enums\UserStatusEnum
 */
export type UserStatus = 'online' | 'away' | 'busy' | 'offline';

/**
 * The status a user explicitly chose, with metadata from Redis. Says nothing
 * about whether they're currently connected — that's answered live by the
 * 'online-users' presence channel in useEchoChannels, not stored here.
 *
 * @see UserStatusService::getStatusWithMetadata()
 */
export interface UserStatusWithMeta {
  /** Current status value */
  status: UserStatus;
  /** ISO 8601 timestamp of when status was last updated */
  updated_at: string;
}

/**
 * User activity types
 * Matches App\Enums\UserActivityTypeEnum
 */
export type UserActivityType =
  | 'status_changed'
  | 'database_created'
  | 'credential_created'
  | 'page_view';

/**
 * User activity record from MySQL
 * Matches App\Models\UserActivity
 */
export interface UserActivity {
  /** KSUID of the activity record */
  id: string;
  /** ID of the user who performed the activity */
  user_id: string;
  /** Type of activity that occurred */
  activity_type: UserActivityType;
  /** Previous status (only for status_changed activities) */
  from_status: UserStatus | null;
  /** New status (only for status_changed activities) */
  to_status: UserStatus | null;
  /** Additional activity-specific metadata */
  metadata: UserActivityMetadata | null;
  /** ISO 8601 timestamp of when activity was created */
  created_at: string;
  /** ISO 8601 timestamp of when activity was last updated */
  updated_at: string;
}

/**
 * Activity metadata based on activity_type
 */
export interface UserActivityMetadata {
  /** For 'database_created': database name and permission */
  database_name?: string;
  permission?: string;

  /** For 'credential_created': credential name and permission */
  credential_name?: string;

  /** For 'page_view': the route path that was viewed */
  path?: string;
}

/**
 * Paginated collection of user activities
 */
export interface UserActivityCollection {
  data: UserActivity[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/**
 * Multiple user statuses indexed by user ID
 * Returns array where key is user ID and value is status
 *
 * @see UserStatusService::getMultipleStatuses()
 */
export type UserStatusMap = Record<string, UserStatus>;

/**
 * User status change event payload
 * Broadcasted via Echo when user status changes
 */
export interface UserStatusChangedEvent {
  /** User ID whose status changed */
  user_id: string;
  /** User display name */
  name: string;
  /** User email */
  email: string;
  /** New status */
  status: UserStatus;
  /** Status display label */
  status_label: string;
  /** Status color */
  status_color: string;
  /** Optional status message */
  message: string;
  /** User avatar URL */
  avatar_url: string | null;
  /** ISO 8601 timestamp of change */
  updated_at: string;
}

/**
 * Namespace for all user status types
 * Provides convenient access via UserStatus.* syntax
 */
export namespace UserStatusTypes {
  export type { UserStatus, UserStatusWithMeta, UserActivity, UserActivityType, UserActivityMetadata, UserActivityCollection, UserStatusMap, UserStatusChangedEvent };
}
