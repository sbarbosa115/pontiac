<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Account;

/**
 * A session's time as the consultant's locale and timezone write it: "martes, 30 de septiembre de 2026" and
 * "10:00 a. m.".
 */
final class SessionTime
{
    public static function date(Account $account, \DateTimeImmutable $at): string
    {
        return (string) (new \IntlDateFormatter($account->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, $account->getTimezone()))->format($at);
    }

    public static function time(Account $account, \DateTimeImmutable $at): string
    {
        return (string) (new \IntlDateFormatter($account->getLocale(), \IntlDateFormatter::NONE, \IntlDateFormatter::SHORT, $account->getTimezone()))->format($at);
    }

    public static function dateTime(Account $account, \DateTimeImmutable $at): string
    {
        return self::date($account, $at).', '.self::time($account, $at);
    }
}
