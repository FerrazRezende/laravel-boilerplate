<?php

declare(strict_types=1);
use App\Models\User;

// Temporary alias: the User model now lives at Modules\Identity\Models\User.
// Modules not yet migrated to their own namespace (and any other code still
// saying `use App\Models\User;`) keep working unchanged until they're swept
// over individually. Delete this file once a repo-wide grep for
// "App\Models\User" turns up nothing.
class_alias(Modules\Identity\Models\User::class, User::class);
