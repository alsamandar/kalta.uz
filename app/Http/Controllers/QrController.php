<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class QrController extends Controller
{
    public function create()
    {
        return view('qr.create', ['svg' => null, 'text' => null]);
    }

    /** JSON for the live preview; a normal page render when JavaScript is off. */
    public function generate(Request $request)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:1000']]);
        $svg = qr_svg($data['text'], __('QR code'), 2);

        if ($request->expectsJson()) {
            return response()->json(['svg' => $svg]);
        }

        return view('qr.create', ['svg' => $svg, 'text' => $data['text']]);
    }
}
