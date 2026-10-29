<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:send-certificate-reminders')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();
