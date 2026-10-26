<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Makes "create something" forms safe to repeat. A phone on a flaky connection (or an impatient double tap)
 * can send the same form twice; the second request with the same _submission id and the same data gets the
 * first one's result instead of creating a duplicate.
 */
class Submission
{
    private const ID = '/^[A-Za-z0-9-]{16,64}$/';

    /**
     * @param  Closure(): (array|string)  $create   does the work once, returns something cacheable (a URL or an array)
     * @param  Closure(array|string): mixed  $respond  turns that result into the HTTP response
     */
    public static function once(Request $request, Closure $create, Closure $respond)
    {
        $id = $request->input('_submission');

        if (! is_string($id) || ! preg_match(self::ID, $id)) {
            return $respond($create());
        }

        // Same id but different data (e.g. Back, edit, submit again) is a new submission
        $key = 'submission:' . sha1($id . '|' . self::fingerprint($request));

        return Cache::lock($key . ':lock', 30)->block(20, function () use ($key, $create, $respond) {
            $result = Cache::get($key);

            if ($result === null) {
                $result = $create();
                Cache::put($key, $result, now()->addMinutes(15));
            }

            return $respond($result);
        });
    }

    /** Redirect for normal form posts, {"redirect": url} for the JavaScript submit. */
    public static function redirect(Request $request, string $url, ?string $flash = null)
    {
        if ($flash) {
            session()->flash('success', $flash);
        }

        return $request->expectsJson() ? response()->json(['redirect' => $url], 201) : redirect($url);
    }

    private static function fingerprint(Request $request): string
    {
        $files = collect($request->allFiles())->map(fn ($file) => is_array($file)
            ? count($file)
            : [$file->getClientOriginalName(), $file->getSize()]);

        return json_encode([$request->except(['_token', '_submission']), $files]);
    }
}
