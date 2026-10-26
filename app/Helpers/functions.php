<?php

use App\Models\Kalta;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * A short code that is easy to read out and type: no 0/O or 1/I/L look-alikes.
 * Grows by one character if the space gets crowded.
 */
function randomString(int $length = 5): string
{
    $alphabet = str_split('23456789ABCDEFGHJKMNPQRSTUVWXYZ');
    $reserved = config('kalta.reserved_words', []);
    $attempts = 0;

    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, count($alphabet) - 1)];
        }

        if (++$attempts % 5 === 0) {
            $length++;
        }
    } while (in_array(Str::lower($code), $reserved, true) || Kalta::where('url', $code)->exists());

    return $code;
}

/**
 * Route URL in the current (or given) language. Pages in the default language
 * live at the root, the others under /ru, /en (route names "ru.home", ...).
 */
function lroute(string $name, mixed $parameters = [], bool $absolute = true, ?string $locale = null): string
{
    $locale ??= app()->getLocale();
    $prefix = $locale === config('kalta.default_locale') ? '' : $locale . '.';

    return route($prefix . $name, $parameters, $absolute);
}

/**
 * The current page in every language, or [] when the page is not localised.
 *
 * @return array<string, string> locale => absolute URL
 */
function locale_alternates(): array
{
    $route = request()->route();
    $name = $route?->getName();

    if (!$name) {
        return [];
    }

    $default = config('kalta.default_locale');
    $base = preg_replace('/^(' . implode('|', array_keys(config('kalta.locales'))) . ')\./', '', $name);
    $urls = [];

    foreach (array_keys(config('kalta.locales')) as $locale) {
        $routeName = $locale === $default ? $base : "$locale.$base";
        if (!Route::has($routeName)) {
            return [];
        }
        $urls[$locale] = route($routeName, $route->parameters());
    }

    return $urls;
}

/** Asset URL with a cache-busting version taken from the file's mtime. */
function asset_v(string $path): string
{
    $file = public_path($path);

    return asset($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** The URL if it is http(s), otherwise null. Never print user URLs without this. */
function safe_url(?string $url): ?string
{
    if (!$url) {
        return null;
    }

    $scheme = strtolower((string) parse_url(trim($url), PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true) ? trim($url) : null;
}

/** "example.com/page" -> "https://example.com/page"; dangerous schemes are left for validation to reject. */
function normalize_url(?string $raw): ?string
{
    $raw = trim((string) $raw);

    if ($raw === '') {
        return null;
    }

    if (str_starts_with($raw, '//')) {
        return 'https:' . $raw;
    }

    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw)
        || preg_match('#^(javascript|data|vbscript|file|mailto|tel|ftp|blob|about):#i', $raw)) {
        return $raw;
    }

    return 'https://' . $raw;
}

/** QR code as inline, responsive SVG markup (no XML prolog, no fixed size). */
function qr_svg(string $text, string $label = 'QR code', int $margin = 1): string
{
    $svg = (string) QrCode::format('svg')->size(300)->margin($margin)->errorCorrection('M')->generate($text);
    $svg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    $svg = preg_replace('/\swidth="\d+"\sheight="\d+"/', '', $svg, 1);

    return preg_replace('/^<svg /', '<svg role="img" aria-label="' . e($label) . '" ', $svg, 1);
}

/** "2M" / "512K" / "1G" from php.ini to bytes. */
function ini_bytes(string|false $value): int
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }

    $n = (int) $value;

    return match (strtolower(substr($value, -1))) {
        'g' => $n * 1024 ** 3,
        'm' => $n * 1024 ** 2,
        'k' => $n * 1024,
        default => $n,
    };
}

/** The upload limit users actually get: our setting, capped by PHP's. */
function upload_limit_kb(): int
{
    $php = array_filter([
        ini_bytes(ini_get('upload_max_filesize')),
        ini_bytes(ini_get('post_max_size')),
    ]);

    $kb = config('kalta.upload_max_kb');

    return $php ? (int) min($kb, floor(min($php) / 1024)) : $kb;
}
