<?php

namespace App\Http\Controllers;

use App\Models\Kalta;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        // "working since" on the home page: the year of the oldest short code, asked once a day
        $since = Cache::remember('home:since', 86400, fn () => Kalta::min('created_at'));

        return view('home', [
            'since' => $since ? (int) substr((string) $since, 0, 4) : null,
        ]);
    }
}
