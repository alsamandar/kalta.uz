<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammingLanguageEnum;
use App\Models\Code;
use App\Support\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CodeController extends Controller
{
    /** Expiry choices in minutes; null means never. */
    public const EXPIRATIONS = [
        'never' => null,
        '10m' => 10,
        '1h' => 60,
        '1d' => 1440,
        '1w' => 10080,
        '1mo' => 43200,
    ];

    public function create()
    {
        return view('codes.create', [
            'languages' => ProgrammingLanguageEnum::cases(),
            'expirations' => array_keys(self::EXPIRATIONS),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:100000'],
            'language' => ['nullable', Rule::enum(ProgrammingLanguageEnum::class)],
            'expiration' => ['nullable', Rule::in(array_keys(self::EXPIRATIONS))],
        ]);

        return Submission::once($request, function () use ($request, $data) {
            $minutes = self::EXPIRATIONS[$data['expiration'] ?? 'never'];

            // Browsers submit textareas with Windows line endings
            $content = str_replace(["\r\n", "\r"], "\n", $data['content']);

            $code = Code::create([
                'content' => $content,
                'visibility' => 'unlisted',
                'prog_lang' => $data['language'] ?? ProgrammingLanguageEnum::TXT->value,
                'expiration' => $minutes ? now()->addMinutes($minutes) : null,
            ]);

            $kalta = $code->kalta()->create([
                'url' => randomString(),
                'user_id' => Auth::id() ?? 1,
                'ip' => $request->ip(),
                'name' => Str::limit(trim(Str::before(trim($content), "\n")), 60, '…'),
            ]);

            return lroute('created', $kalta);
        }, fn (string $url) => Submission::redirect($request, $url));
    }
}
