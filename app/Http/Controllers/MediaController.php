<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Serves uploaded avatars and covers from storage/app/public, so the web server
 * needs no write access to public/ and no storage:link symlink.
 */
class MediaController extends Controller
{
    public function show(string $folder, string $file)
    {
        $path = "$folder/$file";
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
