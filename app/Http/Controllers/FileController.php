<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKaltaRequest;
use App\Models\File;
use App\Support\Submission;
use Illuminate\Support\Facades\Auth;

class FileController extends Controller
{
    public function create()
    {
        return view('files.create', ['maxMb' => max(1, round(upload_limit_kb() / 1024, 1))]);
    }

    public function store(StoreKaltaRequest $request)
    {
        return Submission::once($request, function () use ($request) {
            $upload = $request->file('file');

            // Files are private and only ever served as a download through KaltaController@show, never executed
            // or rendered, so they sit outside the web root under a neutral name.
            $path = $upload->storeAs('uploads', $upload->hashName() . '.bin', File::DISK);

            $file = File::create(['path' => $path, 'name' => basename($upload->getClientOriginalName())]);
            $kalta = $file->kalta()->create([
                'url' => randomString(8), // long enough that nobody finds a file by trying addresses
                'user_id' => Auth::id() ?? 1,
                'ip' => $request->ip(),
                'name' => $file->name,
            ]);

            return ['code' => $kalta->url, 'url' => $kalta->short_url, 'created_url' => lroute('created', $kalta)];
        }, fn (array $result) => $request->expectsJson() ? response()->json($result, 201) : redirect($result['created_url']));
    }
}
