<?php

declare(strict_types=1);

namespace App;

class Constants
{
    public const BIRTHDAY_CALENDAR_URI = 'birthday-calendar';
    public const BIRTHDAY_REMINDER_OFFSET = 'PT9H'; // 9am on the day of the event

    public const MAX_DATE = '2038-01-01'; // Year 2038 bug — will be fixed by https://github.com/tchapi/davis/pull/186
}
