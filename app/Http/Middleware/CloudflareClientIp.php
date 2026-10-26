<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Behind Cloudflare every visitor would otherwise share a handful of edge IPs,
 * which breaks per-IP rate limiting and the IP we store with each link.
 */
class CloudflareClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->headers->get('CF-Connecting-IP');
        $peer = $request->server->get('REMOTE_ADDR');

        if ($client && $peer
            && filter_var($client, FILTER_VALIDATE_IP)
            && IpUtils::checkIp($peer, config('kalta.cloudflare_ranges', []))) {
            $request->server->set('REMOTE_ADDR', $client);
        }

        return $next($request);
    }
}
