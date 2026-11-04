<?php

namespace App\Http\Controllers;

use App\Models\Bio;
use App\Models\Code;
use App\Models\File;
use App\Models\Invitation;
use App\Models\Kalta;
use App\Models\Post;
use App\Models\Short;
use App\Support\Ics;
use App\Support\PreviewImage;
use App\Support\Visitors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File as Files;
use Illuminate\Support\Facades\Storage;

class KaltaController extends Controller
{
    /**
     * Resolve a short code: redirect, download or render, depending on what it points to.
     */
    public function show(Request $request, Kalta $kalta)
    {
        $target = $kalta->kaltaable;
        abort_if($target === null, 404);

        $this->countVisit($request, $kalta);

        if ($target instanceof Short) {
            $url = safe_url($target->long_url);
            abort_if($url === null, 404);

            return redirect()->away($url)->header('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($target instanceof File) {
            $disk = $target->disk();
            abort_if($disk === null, 404);

            // private: never indexed, archived or kept in shared caches, and the address is not passed on
            return $disk->download($target->path, $target->name, [
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'Cache-Control' => 'private, no-store',
                'Referrer-Policy' => 'no-referrer',
            ]);
        }

        if ($target instanceof Bio) {
            return view('bio.show', ['bio' => $target, 'kalta' => $kalta]);
        }

        if ($target instanceof Post) {
            return view('posts.show', ['post' => $target, 'kalta' => $kalta, 'canEdit' => $target->canEdit($request)]);
        }

        if ($target instanceof Code) {
            abort_if($target->isExpired(), 410);

            return response()->view('codes.show', ['code' => $target, 'kalta' => $kalta])
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($target instanceof Invitation) {
            $target->applyLocale();

            $greeting = trim(preg_replace('/\s+/u', ' ', strip_tags((string) request()->query('name', ''))));

            return response()->view('invitations.show', ['invitation' => $target, 'guest' => null, 'kalta' => $kalta, 'greeting' => mb_substr($greeting, 0, 60)])
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        abort(404);
    }

    /** Plain-text version of a text snippet. */
    public function raw(Kalta $kalta)
    {
        $code = $kalta->kaltaable;
        abort_unless($code instanceof Code && !$code->isExpired(), 404);

        return response($code->content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** "Add to calendar" file for an invitation reached through its general link. */
    public function calendar(Kalta $kalta)
    {
        $invitation = $kalta->kaltaable;
        abort_unless($invitation instanceof Invitation, 404);

        $invitation->applyLocale();

        return Ics::response($invitation, $kalta->short_url);
    }

    /** "Your link is ready": the short URL with copy, QR code and share buttons. */
    public function created(Kalta $kalta)
    {
        $target = $kalta->kaltaable;
        abort_if($target === null, 404);

        return view('kalta.created', ['kalta' => $kalta, 'target' => $target]);
    }

    /** Owners can delete their own links from the dashboard. */
    public function destroy(Request $request, Kalta $kalta)
    {
        abort_unless($request->user() && $kalta->user_id === $request->user()->id, 403);

        $target = $kalta->kaltaable;

        if ($target instanceof File) {
            $target->deleteStored();
        } elseif ($target instanceof Bio && $target->avatar_icon) {
            if (str_starts_with($target->avatar_icon, 'media/')) {
                Storage::disk('public')->delete(substr($target->avatar_icon, strlen('media/')));
            } else {
                Files::delete(public_path($target->avatar_icon)); // older uploads live in public/images
            }
        }

        if ($target instanceof Post) {
            PostController::forget($target);

            return back()->with('success', __('Deleted.'));
        }

        if ($target instanceof Invitation) {
            $target->deleteCoverFile();
            PreviewImage::forget($target);
            $target->guests()->delete();
        }

        $target?->delete();
        $kalta->delete();

        return back()->with('success', __('Deleted.'));
    }

    /** Count a human visit; link previews, crawlers and HEAD requests do not count. */
    private function countVisit(Request $request, Kalta $kalta): void
    {
        if (Visitors::isHuman($request)) {
            Kalta::whereKey($kalta->getKey())->increment('clicks');
        }
    }
}
