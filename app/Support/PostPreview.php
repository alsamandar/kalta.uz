<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The picture chat apps and social networks show for a post that has no photo of its own: the title, the first
 * words and the author on the site's cream paper. Drawn once per version and kept on the public disk; the address
 * carries the version, so an edited post gets a fresh picture in chats that cached the old one.
 */
class PostPreview
{
    use GdText;

    /** Bump when the design changes. */
    private const DESIGN = 1;

    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const MARGIN = 96;

    private const PAPER = [255, 250, 243];

    private const BRAND = [255, 117, 0];

    private const BRAND_INK = [168, 74, 0];

    private const INK = [27, 21, 16];

    private const SOFT = [107, 97, 85];

    private const LINE = [234, 223, 206];

    public static function available(): bool
    {
        return function_exists('imagettftext') && is_file(self::font('600-normal', 'latin'));
    }

    public static function version(Post $post): string
    {
        return substr(sha1(json_encode([self::DESIGN, $post->title, $post->author, $post->excerpt, $post->created_at?->toDateString()])), 0, 10);
    }

    /** Address of the picture, with the version so chat apps fetch it again after an edit. */
    public static function url(Post $post): string
    {
        return route('posts.preview', $post) . '?v=' . self::version($post);
    }

    public static function path(Post $post): string
    {
        $disk = Storage::disk('public');
        $path = "previews/post-{$post->id}-" . self::version($post) . '.jpg';

        if ($disk->exists($path)) {
            return $path;
        }

        return Cache::lock("post-preview:{$post->id}", 20)->block(15, function () use ($post, $disk, $path) {
            if (! $disk->exists($path)) {
                $disk->put($path, self::render($post));
                self::forgetOldOnes($post, $path);
            }

            return $path;
        });
    }

    public static function forget(Post $post): void
    {
        self::forgetOldOnes($post, null);
    }

    private static function forgetOldOnes(Post $post, ?string $keep): void
    {
        $disk = Storage::disk('public');

        foreach ($disk->files('previews') as $file) {
            if ($file !== $keep && str_starts_with(basename($file), "post-{$post->id}-")) {
                $disk->delete($file);
            }
        }
    }

    public static function render(Post $post): string
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imageantialias($img, true);
        imagefill($img, 0, 0, self::color($img, self::PAPER));

        // a brand stripe along the top and a hairline frame, like a page torn from a notebook
        imagefilledrectangle($img, 0, 0, self::WIDTH, 12, self::color($img, self::BRAND));
        imagerectangle($img, 36, 48, self::WIDTH - 37, self::HEIGHT - 37, self::color($img, self::LINE));

        $width = self::WIDTH - 2 * self::MARGIN;
        $left = self::MARGIN;

        // title: as big as fits in three lines
        for ($size = 70; $size >= 40; $size -= 4) {
            $title = self::wrap($post->title, '600-normal', $size, $width, 3);
            if (count($title) < 3 || $size <= 44) {
                break;
            }
        }
        $titleLine = $size * 1.22;

        $excerpt = $post->excerpt !== '' && mb_strtolower($post->excerpt) !== mb_strtolower($post->title)
            ? self::wrap($post->excerpt, '400-normal', 30, $width, count($title) >= 3 ? 1 : 2)
            : [];

        $height = 46 + count($title) * $titleLine + ($excerpt ? 24 + count($excerpt) * 42 : 0);
        $y = max(110, (self::HEIGHT - 70 - $height) / 2 + 30);

        self::drawText($img, 'KALTA.UZ', '600-normal', 20, self::BRAND_INK, $left, $y, 6, 'left');
        $y += 46;

        foreach ($title as $line) {
            $y += $titleLine;
            self::drawText($img, $line, '600-normal', $size, self::INK, $left, $y - $size * 0.28, 0, 'left');
        }

        if ($excerpt) {
            $y += 24;
            foreach ($excerpt as $line) {
                $y += 42;
                self::drawText($img, $line, '400-normal', 30, self::SOFT, $left, $y - 10, 0, 'left');
            }
        }

        $foot = array_filter([$post->author, $post->created_at?->format('d.m.Y')]); // digits: the same whoever's language asks first
        if ($foot) {
            self::drawText($img, self::ellipsize(implode('  ·  ', $foot), '400-normal', 26, $width), '400-normal', 26, self::SOFT, $left, self::HEIGHT - 72, 0, 'left');
        }

        imageinterlace($img, true);
        ob_start();
        imagejpeg($img, null, 86);

        return (string) ob_get_clean();
    }
}
