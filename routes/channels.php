<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// User status channel for real-time presence
Broadcast::channel('private-users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Global presence channel for status updates - any authenticated user
Broadcast::channel('private-presence', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});

// True presence channel (client subscribes via Echo.join, not .private()).
// Membership here reflects an actual live WebSocket connection, so it is what
// answers "is this user connected at all" — replacing the old heartbeat/cron.
// It answers a different question than 'private-presence' above, which only
// pushes status the user explicitly chose (online/away/busy/offline).
Broadcast::channel('online-users', function ($user) {
    return ['id' => (string) $user->id, 'name' => $user->name];
});
