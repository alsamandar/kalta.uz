<?php

namespace App\Support;

use App\Models\Invitation;
use Illuminate\Support\Str;

/**
 * Reads a pasted guest list ("Name, e-mail or phone" per line, as typed in a notes app or copied from a
 * spreadsheet) and merges it into an invitation without creating the same person twice.
 */
class GuestList
{
    /**
     * @return array<int, array{name: string, email: ?string, phone: ?string}>
     */
    public static function parse(?string $text): array
    {
        $rows = [];

        foreach (preg_split('/\R/u', (string) $text) as $line) {
            // "1. Aziz", "2) Malika", "- Bobur", "• Dilnoza": list markers are not part of the name
            $line = trim(preg_replace('/^\s*(?:\d{1,3}\s*[.)]|[-•*–—·])\s+/u', '', $line));
            if ($line === '') {
                continue;
            }

            if (preg_match('/[,;\t|]/u', $line)) {
                $parts = array_map('trim', preg_split('/[,;\t|]/u', $line));
                $name = (string) array_shift($parts);
            } else {
                [$name, $parts] = self::splitLoose($line);
            }

            $email = $phone = null;
            foreach ($parts as $part) {
                if (self::isEmail($part)) {
                    $email ??= $part;
                } elseif (self::isPhone($part)) {
                    $phone ??= self::tidyPhone($part);
                }
            }

            $name = Str::limit(trim(preg_replace('/\s+/u', ' ', $name)), 120, '');
            if ($name === '') {
                continue;
            }

            $rows[] = ['name' => $name, 'email' => $email, 'phone' => $phone];
        }

        return $rows;
    }

    /**
     * Add the guests from the text. A name that is already on the list is a duplicate, except that a line
     * with an e-mail or phone fills in what the existing guest is missing ("Aziz" now gets a phone number).
     *
     * @return array{added: int, updated: int, skipped: int, full: int}
     */
    public static function import(Invitation $invitation, ?string $text): array
    {
        $result = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'full' => 0];

        $known = $invitation->guests()->get(['id', 'name', 'email', 'phone']);
        $byName = $known->groupBy(fn ($guest) => self::key($guest->name))->map(fn ($group) => $group->all())->all();
        $room = max(0, config('kalta.max_guests') - $known->count());

        foreach (self::parse($text) as $row) {
            $key = self::key($row['name']);
            $same = $byName[$key] ?? [];

            if (self::alreadyListed($same, $row)) {
                $result['skipped']++;
                continue;
            }

            if ($guest = self::fillable($same, $row)) {
                $guest->update(array_filter([
                    'email' => $guest->email ?: $row['email'],
                    'phone' => $guest->phone ?: $row['phone'],
                ]));
                $result['updated']++;
                continue;
            }

            if ($room <= 0) {
                $result['full']++;
                continue;
            }

            $byName[$key][] = $invitation->guests()->create($row);
            $room--;
            $result['added']++;
        }

        return $result;
    }

    public static function isEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isPhone(string $value): bool
    {
        $digits = strlen(preg_replace('/\D/', '', $value));

        return preg_match('/^\+?[\d\s().-]{7,24}$/', $value) === 1 && $digits >= 7 && $digits <= 15;
    }

    /** Digits only, to compare phone numbers typed in different styles. */
    public static function digits(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone);
    }

    /** Names are compared without case, spacing or the many apostrophe look-alikes used in Uzbek. */
    public static function key(string $name): string
    {
        return (string) Str::of($name)
            ->lower()
            ->replace(["’", "‘", 'ʻ', 'ʼ', '`', '´', '′'], "'")
            ->squish();
    }

    /**
     * "Aziz Karimov +998 90 123 45 67" and "Aziz Karimov aziz@example.com": no separator, the contact is
     * recognised by what it looks like.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private static function splitLoose(string $line): array
    {
        $contacts = [];

        if (preg_match('/[^\s,;|]+@[^\s,;|]+\.[^\s,;|]+/u', $line, $match) && self::isEmail($match[0])) {
            $contacts[] = $match[0];
            $line = trim(str_replace($match[0], ' ', $line));
        }

        // a phone number at the end, optionally after " - " or ":"
        if (preg_match('/^(.*?)[\s:–—-]*(\+?\(?\d[\d\s().-]{5,}\d)\s*$/u', $line, $match) && self::isPhone($match[2])) {
            $contacts[] = $match[2];
            $line = trim($match[1]);
        } elseif (preg_match('/^(.*?)\s+[–—:-]\s+(.+)$/u', $line, $match) && (self::isPhone($match[2]) || self::isEmail($match[2]))) {
            $contacts[] = $match[2];
            $line = trim($match[1]);
        }

        // not trim($line, "-–—"): trim() works on bytes and would eat the last byte of letters such as "р"
        return [trim(preg_replace('/^[\s:–—-]+|[\s:–—-]+$/u', '', $line)), $contacts];
    }

    private static function tidyPhone(string $phone): string
    {
        return trim(preg_replace('/\s+/', ' ', $phone));
    }

    /** True when one of the guests with this name is this very person (same e-mail or phone, or no contact given). */
    private static function alreadyListed(array $same, array $row): bool
    {
        if ($same === []) {
            return false;
        }

        if ($row['email'] === null && $row['phone'] === null) {
            return true;
        }

        foreach ($same as $guest) {
            $sameEmail = $row['email'] !== null && $guest->email !== null && strcasecmp($guest->email, $row['email']) === 0;
            $samePhone = $row['phone'] !== null && $guest->phone !== null && self::digits($guest->phone) === self::digits($row['phone']);

            // a line that adds nothing new to a guest is a repeat as well
            $adds = ($row['email'] !== null && $guest->email === null) || ($row['phone'] !== null && $guest->phone === null);

            if (($sameEmail || $samePhone) && ! $adds) {
                return true;
            }
        }

        return false;
    }

    /** A guest with this name who lacks the contact the line provides (and does not contradict it). */
    private static function fillable(array $same, array $row)
    {
        foreach ($same as $guest) {
            $contradicts = ($row['email'] !== null && $guest->email !== null && strcasecmp($guest->email, $row['email']) !== 0)
                || ($row['phone'] !== null && $guest->phone !== null && self::digits($guest->phone) !== self::digits($row['phone']));
            $adds = ($row['email'] !== null && $guest->email === null) || ($row['phone'] !== null && $guest->phone === null);

            if ($adds && ! $contradicts) {
                return $guest;
            }
        }

        return null;
    }
}
