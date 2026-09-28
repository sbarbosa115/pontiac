<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\BookingSession;

/**
 * A session as an iCalendar file (RFC 5545), attached to the booking emails so the person adds it to their calendar
 * in one tap. Its UID is the session's id: a rescheduled or cancelled session updates the same calendar entry.
 */
final class CalendarFile
{
    public static function for(BookingSession $session, string $title, string $organizer, string $description, bool $cancelled = false): string
    {
        $utc = static fn (\DateTimeImmutable $at): string => $at->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Pontiac//Sesiones//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:'.($cancelled ? 'CANCEL' : 'PUBLISH'),
            'BEGIN:VEVENT',
            'UID:'.$session->getId()->toRfc4122().'@pontiac',
            'DTSTAMP:'.$utc(new \DateTimeImmutable()),
            // A later version replaces an earlier one in the person's calendar.
            'SEQUENCE:'.$session->getScheduledAt()->getTimestamp(),
            'DTSTART:'.$utc($session->getStartsAt()),
            'DTEND:'.$utc($session->getEndsAt()),
            'SUMMARY:'.self::escape($title),
            'DESCRIPTION:'.self::escape($description),
            'ORGANIZER;CN='.self::escape($organizer).':mailto:no-reply@pontiac.co',
        ];
        if ('' !== $session->getMeetingLink()) {
            $lines[] = 'LOCATION:'.self::escape($session->getMeetingLink());
        }
        $lines[] = 'STATUS:'.($cancelled ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function escape(string $text): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\\,', '\\n', '\\n'], $text);
    }

    /** Lines of at most 75 octets, continued with a space (RFC 5545, 3.1). */
    private static function fold(string $line): string
    {
        $out = '';
        while (\strlen($line) > 75) {
            $cut = 75;
            // Never split a UTF-8 character.
            while ($cut > 0 && (\ord($line[$cut]) & 0xC0) === 0x80) {
                --$cut;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
