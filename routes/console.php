<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Presence\Models\UserActivity;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// model:prune only discovers models under app/Models, so module models are
// listed by hand. Runs in the `scheduler` Compose service.
Schedule::command('model:prune', ['--model' => [UserActivity::class]])->daily();
