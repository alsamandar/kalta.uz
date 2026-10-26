<?php

namespace App\Http\Controllers;

use App\Models\Kalta;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $kaltas = Kalta::with('kaltaable')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return view('dashboard', compact('kaltas'));
    }
}
