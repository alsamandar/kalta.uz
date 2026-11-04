<?php

namespace App\Models;

use App\Support\PostHtml;
use App\Traits\Kaltaable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A Telegraph-style article: a title, an optional author line and a body of simple formatted text with pictures.
 * Anyone can publish one without an account; it lives at a readable address such as kalta.uz/my-first-post-11-04.
 */
class Post extends Model
{
    use HasFactory, Kaltaable;

    /** Shorter posts are still published, but kept out of search engines as thin content. */
    public const MIN_WORDS_TO_INDEX = 50;

    /** Cookie holding the edit tokens of the posts written in this browser without an account. */
    public const COOKIE = 'kalta_posts';

    private const COOKIE_KEEP = 50;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            $post->edit_token ??= Str::random(40);
        });

        // excerpt, words and image always follow the body
        static::saving(function (Post $post) {
            $post->excerpt = PostHtml::excerpt($post->body);
            $post->words = PostHtml::words($post->body);
            $post->image = PostHtml::firstImage($post->body);
        });
    }

    public function indexable(): bool
    {
        return $this->words >= self::MIN_WORDS_TO_INDEX;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset($this->image) : null;
    }

    /** @return array{0: int, 1: int}|null width and height of the first picture */
    public function imageSize(): ?array
    {
        $size = $this->image ? @getimagesize(Storage::disk('public')->path(substr($this->image, strlen('media/')))) : false;

        return $size ? [$size[0], $size[1]] : null;
    }

    public function readingMinutes(): int
    {
        return max(1, (int) round($this->words / 200));
    }

    /** Images this post uses, as paths on the public disk ("posts/..."). */
    public function imagePaths(): array
    {
        return array_map(fn ($src) => substr($src, strlen('media/')), PostHtml::images($this->body));
    }

    /** The owner (logged in when they wrote it), or the browser it was written in. */
    public function canEdit(Request $request): bool
    {
        $owner = $this->kalta?->user_id;

        if ($request->user() && $owner === $request->user()->id) {
            return true;
        }

        return in_array($this->edit_token, self::tokensFrom($request), true);
    }

    /** @return string[] */
    public static function tokensFrom(Request $request): array
    {
        $tokens = json_decode((string) $request->cookie(self::COOKIE), true);

        return is_array($tokens) ? array_values(array_filter($tokens, 'is_string')) : [];
    }

    /** The cookie with this post's token added (the oldest ones drop off). */
    public function rememberIn(Request $request): \Symfony\Component\HttpFoundation\Cookie
    {
        $tokens = array_values(array_unique([...self::tokensFrom($request), $this->edit_token]));
        $tokens = array_slice($tokens, -self::COOKIE_KEEP);

        return cookie(self::COOKIE, json_encode($tokens), 60 * 24 * 365 * 5);
    }

    /** "my-first-post-11-04", like Telegraph; "-2", "-3"... when it is taken; a random code for titles with no letters. */
    public static function addressFor(string $title): string
    {
        $base = Str::of(Str::slug(Str::ascii($title)))->limit(60, '')->trim('-')->value();

        if ($base === '') {
            return randomString(8);
        }

        $base .= '-' . now(config('kalta.timezone'))->format('m-d');
        $reserved = config('kalta.reserved_words', []);

        for ($n = 1; ; $n++) {
            $candidate = $n === 1 ? $base : "$base-$n";

            if (! in_array($candidate, $reserved, true) && ! Kalta::where('url', $candidate)->exists()) {
                return $candidate;
            }
        }
    }
}
