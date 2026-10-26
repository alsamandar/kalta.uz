<?php

namespace App\Http\Controllers;

use App\Models\Bio;
use App\Models\Kalta;

class SeoController extends Controller
{
    /** Only link-in-bio pages are meant to appear in search results; every other page is noindex. */
    public function sitemap()
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        Kalta::query()->where('kaltaable_type', Bio::class)->orderBy('id')->chunk(500, function ($kaltas) use (&$xml) {
            foreach ($kaltas as $kalta) {
                $xml[] = '<url><loc>' . e($kalta->short_url) . '</loc><lastmod>' . ($kalta->updated_at ?? now())->toDateString() . '</lastmod></url>';
            }
        });

        $xml[] = '</urlset>';

        return response(implode("\n", $xml), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots()
    {
        if (!config('kalta.seo_index')) {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $body = implode("\n", [
                'User-agent: *',
                'Disallow: /admin',
                'Disallow: /dashboard',
                'Disallow: /profile',
                'Disallow: /login',
                'Disallow: /register',
                'Disallow: /forgot-password',
                'Disallow: /i/',
                'Disallow: /invitations/',
                'Disallow: /lang/',
                'Disallow: /created/',
                'Disallow: /*/created/',
                'Allow: /',
                '',
                'Sitemap: ' . url('/sitemap.xml'),
                '',
            ]);
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
