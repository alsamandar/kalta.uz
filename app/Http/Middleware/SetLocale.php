<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Pick the UI language. Localised routes pass it explicitly (locale:ru);
     * everything else falls back to the visitor's cookie, then Accept-Language.
     */
    public function handle(Request $request, Closure $next, ?string $explicit = null): Response
    {
        $locales = array_keys(config('kalta.locales'));

        // /ru/... and /en/... always mean that language, even for a 404
        $prefix = $request->segment(1);
        if ($explicit === null && $prefix !== config('kalta.default_locale') && in_array($prefix, $locales, true)) {
            $explicit = $prefix;
        }

        $fromUrl = $explicit !== null && in_array($explicit, $locales, true);

        if ($fromUrl) {
            $locale = $explicit;
        } else {
            $cookie = $request->cookie('lang');
            $locale = in_array($cookie, $locales, true)
                ? $cookie
                : ($request->getPreferredLanguage($locales) ?: config('kalta.default_locale'));
        }

        app()->setLocale($locale);
        Carbon::setLocale(config("kalta.carbon_locales.$locale", $locale));

        $response = $next($request);

        // Remember an explicit choice for pages that have no language in the URL
        if ($fromUrl && $request->isMethod('GET') && $request->cookie('lang') !== $locale) {
            $response->headers->setCookie(cookie('lang', $locale, 60 * 24 * 365));
        }

        return $response;
    }
}
