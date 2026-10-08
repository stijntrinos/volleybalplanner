<?php

use App\Models\ContactRequest;
use App\Models\Invitation;
use App\Planner\Models\ChangeRequest;
use Illuminate\Support\Facades\Schedule;

// Personal data that has served its purpose (AVG): old invitations, change
// requests after a season, contact requests after one to two years.
Schedule::command('model:prune', ['--model' => [Invitation::class, ChangeRequest::class, ContactRequest::class]])->dailyAt('03:15');

// The activity log keeps a year (activitylog.clean_after_days).
Schedule::command('activitylog:clean')->dailyAt('03:30');
