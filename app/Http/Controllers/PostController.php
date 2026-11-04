<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\Images;
use App\Support\PostHtml;
use App\Support\PostPreview;
use App\Support\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Telegraph-style posts: write, publish, get a readable address. No account needed; the browser that wrote a post
 * can edit it (an encrypted cookie keeps its edit token), and a logged-in author can edit it from anywhere.
 */
class PostController extends Controller
{
    private const MAX_TEXT = 60000;

    public function create()
    {
        return view('posts.edit', ['post' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return Submission::once($request, function () use ($request, $data) {
            $post = Post::create($data);
            $kalta = $post->kalta()->create([
                'url' => Post::addressFor($post->title),
                'user_id' => Auth::id() ?? 1,
                'ip' => $request->ip(),
                'name' => $post->title,
            ]);

            return ['url' => $kalta->short_url, 'token' => $post->edit_token];
        }, function (array $result) use ($request) {
            $post = Post::where('edit_token', $result['token'])->firstOrFail();
            session()->flash('post-published', true); // the editor's saved draft can go

            return Submission::redirect($request, $result['url'], __('Published! Only this browser and your account (if you are logged in) can edit the post.'))
                ->withCookie($post->rememberIn($request));
        });
    }

    public function edit(Request $request, Post $post)
    {
        abort_unless($post->canEdit($request), 403);

        return view('posts.edit', ['post' => $post]);
    }

    public function update(Request $request, Post $post)
    {
        abort_unless($post->canEdit($request), 403);

        $before = $post->imagePaths();
        $post->update($this->validated($request));
        $post->kalta?->update(['name' => $post->title]);
        $this->deleteImages(array_diff($before, $post->imagePaths()));

        return Submission::redirect($request, $post->kalta->short_url, __('Saved.'));
    }

    public function destroy(Request $request, Post $post)
    {
        abort_unless($post->canEdit($request), 403);

        self::forget($post);

        return redirect(lroute('posts.create'))->with('success', __('Deleted.'));
    }

    /** A picture dropped into the editor: shrunk, stored, and its address handed back for the <img>. */
    public function image(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ], [], ['image' => __('picture')]);

        $path = Images::post($request->file('image'));

        return response()->json(['src' => '/' . $path], 201);
    }

    /** The picture chat apps show for a post without a photo of its own. */
    public function preview(Post $post)
    {
        abort_unless(PostPreview::available(), 404);

        $headers = ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400'];

        try {
            return response()->file(Storage::disk('public')->path(PostPreview::path($post)), $headers);
        } catch (\Throwable $e) {
            // e.g. the storage folder is not writable: still hand out the picture, just without keeping it
            Log::warning('Post preview could not be stored', ['post' => $post->id, 'error' => $e->getMessage()]);

            return response(PostPreview::render($post), 200, array_merge($headers, ['Cache-Control' => 'public, max-age=600']));
        }
    }

    /** Deletes the post with its address, pictures and preview (also used by the dashboard). */
    public static function forget(Post $post): void
    {
        (new self)->deleteImages($post->imagePaths(), $post);
        PostPreview::forget($post);
        $post->kalta?->delete();
        $post->delete();
    }

    /** @param  Post|null  $leaving  a post that is being deleted: its own use of a picture does not count */
    private function deleteImages(array $paths, ?Post $leaving = null): void
    {
        // a picture can be shared by two posts (copied from one into another): keep it while another one uses it
        foreach ($paths as $path) {
            $usedElsewhere = Post::where('body', 'like', '%' . $path . '%')
                ->when($leaving, fn ($query) => $query->whereKeyNot($leaving->getKey()))
                ->exists();

            if (! $usedElsewhere) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /** @return array{title: string, author: ?string, body: string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:400000'],
            'format' => ['nullable', 'in:html,text'],
        ], [], [
            'title' => __('title'),
            'author' => __('author'),
            'body' => __('text'),
        ]);

        $body = ($data['format'] ?? 'text') === 'html' ? PostHtml::clean($data['body']) : PostHtml::fromText($data['body']);

        if (PostHtml::text($body) === '' && PostHtml::images($body) === []) {
            throw ValidationException::withMessages(['body' => __('Write something first.')]);
        }

        if (mb_strlen(PostHtml::text($body)) > self::MAX_TEXT) {
            throw ValidationException::withMessages(['body' => __('The post is too long (at most :count characters).', ['count' => number_format(self::MAX_TEXT, 0, '.', ' ')])]);
        }

        return [
            'title' => trim(preg_replace('/\s+/u', ' ', $data['title'])),
            'author' => ($author = trim(preg_replace('/\s+/u', ' ', (string) ($data['author'] ?? '')))) === '' ? null : $author,
            'body' => $body,
        ];
    }
}
