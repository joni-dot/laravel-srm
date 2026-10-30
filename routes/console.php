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
