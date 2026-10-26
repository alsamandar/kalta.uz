<?php

namespace App\Support;

use App\Models\Invitation;
use Illuminate\Http\Response;

/**
 * An iCalendar (.ics) file for the wedding. Apple Calendar, Outlook and Google Calendar all open it, which a
 * "calendar.google.com" link does not do for the many guests on iPhones.
 */
class Ics
{
    /** The .ics as a response: iPhones offer "Add to Calendar" for it instead of downloading a file. */
    public static function response(Invitation $invitation, string $url): Response
    {
        return response(self::wedding($invitation, $url), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="wedding.ics"',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public static function wedding(Invitation $invitation, string $url): string
    {
        $start = $invitation->event_at->copy()->utc();
        $summary = __('Wedding of :couple', ['couple' => $invitation->coupleNames()]);
        $description = trim($invitation->message . "\n\n" . $url);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kalta.uz//Wedding invitation//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:invitation-' . $invitation->id . '@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'kalta.uz'),
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $start->format('Ymd\THis\Z'),
            'DTEND:' . $start->copy()->addHours(4)->format('Ymd\THis\Z'),
            'SUMMARY:' . self::escape($summary),
            'LOCATION:' . self::escape(trim($invitation->venue . ', ' . $invitation->address, ', ')),
            'DESCRIPTION:' . self::escape($description),
            'URL:' . $url,
            'BEGIN:VALARM',
            'TRIGGER:-P1D',
            'ACTION:DISPLAY',
            'DESCRIPTION:' . self::escape($summary),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    private static function escape(string $text): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Lines are at most 75 bytes; longer ones continue on the next line after a space (never inside a character). */
    private static function fold(string $line): string
    {
        $parts = [];
        $limit = 75;

        while (strlen($line) > $limit) {
            $chunk = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $chunk;
            $line = substr($line, strlen($chunk));
            $limit = 74;
        }

        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}
