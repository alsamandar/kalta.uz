<?php

namespace App\Support;

use Illuminate\Http\Request;

class Visitors
{
    /**
     * A person looking at a page. Link previews (Telegram, WhatsApp, iMessage...), crawlers, monitors and
     * HEAD requests are not, so they neither count as clicks nor as "the guest opened the invitation".
     * Hosts checking their own invitation add "?preview" so that they do not count either.
     */
    public static function isHuman(Request $request): bool
    {
        $agent = strtolower((string) $request->userAgent());

        if ($request->isMethod('HEAD') || $request->has('preview') || $agent === '') {
            return false;
        }

        return ! preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|telegram|whatsapp|curl|wget|python-requests|headless/', $agent);
    }
}
