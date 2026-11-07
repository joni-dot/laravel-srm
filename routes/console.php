<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:renew-contracts')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('app:send-contract-reminders')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('app:send-certificate-reminders')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

// Expired and revoked API tokens are useless; removing them keeps the token table small.
Schedule::command('passport:purge')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->onOneServer();
