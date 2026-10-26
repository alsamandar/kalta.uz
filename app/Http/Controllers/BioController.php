<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBioRequest;
use App\Models\Bio;
use App\Support\Images;
use App\Support\Submission;
use Illuminate\Support\Facades\Auth;

class BioController extends Controller
{
    public function create()
    {
        return view('bio.create', ['themes' => Bio::THEMES]);
    }

    public function store(StoreBioRequest $request)
    {
        return Submission::once($request, function () use ($request) {
            $data = $request->safe()->only(['title', 'style', ...Bio::LINKS]);
            $data['style'] ??= 'default';

            if ($request->hasFile('avatar_icon')) {
                $data['avatar_icon'] = Images::avatar($request->file('avatar_icon'));
            }

            $bio = Bio::create($data);
            $kalta = $bio->kalta()->create([
                'url' => randomString(),
                'user_id' => Auth::id() ?? 1,
                'ip' => $request->ip(),
                'description' => $request->validated('description'),
                'name' => $bio->title,
            ]);

            return lroute('created', $kalta);
        }, fn (string $url) => Submission::redirect($request, $url));
    }

    /** Old /bio/{id} addresses: send people to the short link so there is one URL per page. */
    public function show(Bio $bio)
    {
        if ($kalta = $bio->kalta) {
            return redirect($kalta->short_url, 301);
        }

        return view('bio.show', ['bio' => $bio, 'kalta' => null]);
    }
}
