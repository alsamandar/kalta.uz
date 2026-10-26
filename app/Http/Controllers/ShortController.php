<?php

namespace App\Http\Controllers;

use App\Models\Short;
use Illuminate\Http\Request;
use App\Support\Submission;
use Illuminate\Support\Facades\Auth;

class ShortController extends Controller
{
    public function make(Request $request)
    {
        // Let people type "example.com" without the scheme
        $request->merge(['url' => normalize_url($request->input('url'))]);

        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        return Submission::once($request, function () use ($request, $data) {
            $short = Short::create(['long_url' => $data['url']]);
            $kalta = $short->kalta()->create([
                'url' => randomString(),
                'user_id' => Auth::id() ?? 1,
                'ip' => $request->ip(),
            ]);

            return [
                'code' => $kalta->url,
                'url' => $kalta->short_url,
                'long_url' => $data['url'],
                'created_url' => lroute('created', $kalta),
                'qr' => qr_svg($kalta->short_url, __('QR code for :url', ['url' => $kalta->short_url])),
            ];
        }, fn (array $result) => $request->expectsJson() ? response()->json($result, 201) : redirect($result['created_url']));
    }
}
