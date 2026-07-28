<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShortRequest;
use App\Models\Short;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ShortController extends Controller
{
    public function make(StoreShortRequest $request)
    {
        $url = $request->validated('url');
        $randomString = randomString();
        $short = Short::create(['long_url' => $url]);
        $short->kalta()->create([
            'url' => $randomString,
            'user_id' => 1,
            'ip' => $request->ip()
        ]);

        $qrCode = QrCode::size(300)->generate(asset($randomString));

        // Return the view with the generated QR code
        return view('kalta.qr', compact('qrCode', 'randomString', 'url'));
    }
}