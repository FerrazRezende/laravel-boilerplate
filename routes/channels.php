<?php

use Illuminate\Support\Facades\Broadcast;

// Status updates broadcast here — any authenticated user may watch.
//
// Register the name WITHOUT the `private-` prefix. Laravel strips exactly one
// `private-` from the incoming channel before matching these patterns, and
// Echo's `private()` adds exactly one on the way out. Writing the prefix here
// forces the client to pass it too, which then arrives doubled and matches
// nothing: the subscription still authorizes, so it fails silently.
Broadcast::channel('presence', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});

// True presence channel (client subscribes via Echo.join, not .private()).
// Membership here reflects an actual live WebSocket connection, so it is what
// answers "is this user connected at all" — replacing the old heartbeat/cron.
// It answers a different question than 'presence' above, which only
// pushes status the user explicitly chose (online/away/busy/offline).
Broadcast::channel('online-users', function ($user) {
    return ['id' => (string) $user->id, 'name' => $user->name];
});

// Live job progress (Observability module). Operators watch every job; a user
// only ever gets the jobs they dispatched themselves.
Broadcast::channel('jobs-admin', function ($user) {
    return (bool) $user->is_admin;
});

Broadcast::channel('jobs.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});
