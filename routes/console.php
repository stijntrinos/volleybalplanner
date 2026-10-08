<?php

use App\Models\Invitation;
use Illuminate\Support\Facades\Schedule;

// Old invitations: expired ones and accepted ones after a month.
Schedule::command('model:prune', ['--model' => [Invitation::class]])->dailyAt('03:15');

// The activity log keeps a year (activitylog.clean_after_days).
Schedule::command('activitylog:clean')->dailyAt('03:30');
