<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammingLanguageEnum;
use App\Http\Requests\StoreCodeRequest;
use App\Models\Code;

class CodeController extends Controller
{
    public function create()
    {
        return view('codes.create'); // Form to submit code
    }

    public function store(StoreCodeRequest $request)
    {
        $code = new Code();
        $code->content = $request->validated('content');
        $code->visibility = $request->validated('visibility') ?? 'public';
        $code->prog_lang = ProgrammingLanguageEnum::TXT;
        $expiration = $request->validated('expiration');
        $code->expiration = $expiration ? now()->addMinutes($expiration) : null;
        $code->save();
        return redirect()->route('code.show', $code->id);
    }

    public function show($id)
    {
        $code = Code::findOrFail($id);
        return view('show', ['code' => $code]);
    }
}
