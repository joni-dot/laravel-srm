<?php

namespace App\Enums;

enum SettingKey: string
{
    /** Where certificate reminders go when a supplier has no responsible buyer. */
    case ReminderEmail = 'reminder_email';
}
