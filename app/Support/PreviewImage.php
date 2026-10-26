<?php

namespace App\Support;

use App\Models\Invitation;
use GdImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The picture chat apps show when somebody shares an invitation link (Telegram, WhatsApp, iMessage...): the names,
 * date, time, venue and address, beside the cover photo when there is one, in the same look as the invitation.
 *
 * It is drawn once per version of the details and kept on the public disk. The picture's address carries that
 * version (see version()), so a chat app that cached an old picture is handed a new address after an edit.
 */
class PreviewImage
{
    /** Bump when the design changes: pictures drawn with the old design are drawn again. */
    private const DESIGN = 1;

    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const PHOTO_WIDTH = 460;

    private const PAPER = [251, 246, 236];

    private const GOLD = [138, 100, 40];

    private const GOLD_SOFT = [214, 187, 134];

    private const INK = [58, 46, 34];

    private const SOFT = [107, 90, 70];

    /** Needs GD with FreeType (for the letters) and the bundled fonts. */
    public static function available(): bool
    {
        return function_exists('imagettftext') && is_file(self::font('600-italic', 'latin'));
    }

    /** Changes whenever anything that is drawn changes. */
    public static function version(Invitation $invitation): string
    {
        $cover = $invitation->cover_image;
        $disk = Storage::disk('public');
        $coverState = $cover && str_starts_with($cover, 'media/') && $disk->exists(substr($cover, 6))
            ? [$cover, $disk->size(substr($cover, 6))]
            : null;

        return substr(sha1(json_encode([
            self::DESIGN,
            $invitation->groom_name,
            $invitation->bride_name,
            $invitation->event_at?->copy()->utc()->format('Y-m-d H:i'),
            $invitation->venue,
            $invitation->address,
            $invitation->locale,
            $coverState,
        ])), 0, 10);
    }

    /** Where the current picture lives on the public disk; it is drawn now if it does not exist yet. */
    public static function path(Invitation $invitation): string
    {
        $disk = Storage::disk('public');
        $path = "previews/{$invitation->id}-" . self::version($invitation) . '.jpg';

        if ($disk->exists($path)) {
            return $path;
        }

        // two chat apps asking at the same moment must not both draw it
        return Cache::lock("preview:{$invitation->id}", 20)->block(15, function () use ($invitation, $disk, $path) {
            if (! $disk->exists($path)) {
                $disk->put($path, self::render($invitation));
                self::forgetOldOnes($invitation, $path);
            }

            return $path;
        });
    }

    /** Remove every stored picture of this invitation (it was deleted). */
    public static function forget(Invitation $invitation): void
    {
        self::forgetOldOnes($invitation, null);
    }

    private static function forgetOldOnes(Invitation $invitation, ?string $keep): void
    {
        $disk = Storage::disk('public');

        foreach ($disk->files('previews') as $file) {
            if ($file !== $keep && str_starts_with(basename($file), "{$invitation->id}-")) {
                $disk->delete($file);
            }
        }
    }

    /** The JPEG itself. */
    public static function render(Invitation $invitation): string
    {
        $invitation->applyLocale(); // the picture speaks the invitation's language, whoever asks for it

        $local = $invitation->localEventAt();
        $date = self::upperFirst($local->translatedFormat('l, j F Y'));
        $time = $local->format('H:i');
        $kicker = mb_strtoupper(__('Wedding invitation'));

        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imageantialias($img, true);
        imagefill($img, 0, 0, self::color($img, self::PAPER));

        $photo = self::drawPhoto($img, $invitation);
        $left = $photo ? self::PHOTO_WIDTH : 0;
        $areaWidth = self::WIDTH - $left;
        $centre = $left + $areaWidth / 2;
        $textWidth = $areaWidth - ($photo ? 150 : 300);

        self::drawFrame($img, $left);

        // Measure first, then place the whole block in the middle of the card. If it is too tall (long venue, long
        // names) everything shrinks a little and is measured again.
        $limit = self::HEIGHT - 150;
        $scale = 1.0;
        do {
            $blocks = self::layout($invitation, $date, $time, $kicker, $textWidth, $scale);
            $height = array_sum(array_column($blocks, 'height'));
            $scale -= 0.06;
        } while ($height > $limit && $scale > 0.55);

        $y = (self::HEIGHT - $height) / 2;
        foreach ($blocks as $block) {
            ($block['draw'])($img, $centre, $y);
            $y += $block['height'];
        }

        imageinterlace($img, true); // progressive: shows up faster in chat apps
        ob_start();
        imagejpeg($img, null, 86);

        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------ layout

    /**
     * The card from top to bottom: each block knows its height and how to draw itself centred at ($cx, $top).
     *
     * @return array<int, array{height: float, draw: \Closure}>
     */
    private static function layout(Invitation $invitation, string $date, string $time, string $kicker, float $width, float $scale): array
    {
        $blocks = [];

        $blocks[] = [
            'height' => 60 * $scale,
            'draw' => fn (GdImage $img, float $cx, float $top) => self::drawText($img, $kicker, '600-normal', 21 * $scale, self::GOLD, $cx, $top + 22 * $scale, 5),
        ];

        $names = self::names($invitation->groom_name, $invitation->bride_name, $width, $scale);
        $blocks[] = ['height' => $names['height'] + 30 * $scale, 'draw' => fn (GdImage $img, float $cx, float $top) => ($names['draw'])($img, $cx, $top)];

        $blocks[] = [
            'height' => 48 * $scale,
            'draw' => fn (GdImage $img, float $cx, float $top) => self::drawOrnament($img, $cx, $top + 12 * $scale, 150 * $scale),
        ];

        $blocks[] = [
            'height' => 56 * $scale,
            'draw' => fn (GdImage $img, float $cx, float $top) => self::drawText($img, $date, '600-normal', self::fit($date, '600-normal', 40 * $scale, $width, 26), self::INK, $cx, $top + 36 * $scale),
        ];
        $blocks[] = [
            'height' => 54 * $scale,
            'draw' => fn (GdImage $img, float $cx, float $top) => self::drawText($img, $time, '600-italic', 38 * $scale, self::SOFT, $cx, $top + 30 * $scale),
        ];

        $venue = self::wrap($invitation->venue, '600-normal', 34 * $scale, $width, 2);
        $blocks[] = [
            'height' => count($venue) * 42 * $scale + 8 * $scale,
            'draw' => function (GdImage $img, float $cx, float $top) use ($venue, $scale) {
                foreach ($venue as $i => $line) {
                    self::drawText($img, $line, '600-normal', 34 * $scale, self::INK, $cx, $top + (30 + $i * 42) * $scale);
                }
            },
        ];

        if (trim((string) $invitation->address) !== '') {
            $address = self::wrap((string) $invitation->address, '400-normal', 27 * $scale, $width, 2);
            $blocks[] = [
                'height' => count($address) * 34 * $scale,
                'draw' => function (GdImage $img, float $cx, float $top) use ($address, $scale) {
                    foreach ($address as $i => $line) {
                        self::drawText($img, $line, '400-normal', 27 * $scale, self::SOFT, $cx, $top + (24 + $i * 34) * $scale);
                    }
                },
            ];
        }

        return $blocks;
    }

    /**
     * "Samandar & Sarvinoz" on one line when it fits at a good size, otherwise one name above the other.
     *
     * @return array{height: float, draw: \Closure}
     */
    private static function names(string $groom, string $bride, float $width, float $scale): array
    {
        $italic = '600-italic';

        for ($size = 88; $size >= 54; $size -= 4) {
            $s = $size * $scale;
            $gap = $s * 0.28;
            $total = self::width($groom, $italic, $s) + $gap + self::width('&', '400-normal', $s * 0.62) + $gap + self::width($bride, $italic, $s);

            if ($total <= $width) {
                return [
                    'height' => $s * 1.05,
                    'draw' => function (GdImage $img, float $cx, float $top) use ($groom, $bride, $italic, $s, $gap, $total) {
                        $x = $cx - $total / 2;
                        $base = $top + $s * 0.82;
                        $x += self::drawText($img, $groom, $italic, $s, self::GOLD, $x, $base, 0, 'left') + $gap;
                        $x += self::drawText($img, '&', '400-normal', $s * 0.62, self::GOLD_SOFT, $x, $base, 0, 'left') + $gap;
                        self::drawText($img, $bride, $italic, $s, self::GOLD, $x, $base, 0, 'left');
                    },
                ];
            }
        }

        $s = self::fit($groom . "\n" . $bride, $italic, 70 * $scale, $width, 36 * $scale, true);

        return [
            'height' => $s * 2.35,
            'draw' => function (GdImage $img, float $cx, float $top) use ($groom, $bride, $italic, $s, $width) {
                self::drawText($img, self::ellipsize($groom, $italic, $s, $width), $italic, $s, self::GOLD, $cx, $top + $s * 0.82);
                self::drawText($img, '&', '400-normal', $s * 0.55, self::GOLD_SOFT, $cx, $top + $s * 1.5);
                self::drawText($img, self::ellipsize($bride, $italic, $s, $width), $italic, $s, self::GOLD, $cx, $top + $s * 2.3);
            },
        ];
    }

    // ------------------------------------------------------------------ drawing

    /** The cover photo fills the left column, keeping the upper part of tall photos (heads matter more than feet). */
    private static function drawPhoto(GdImage $img, Invitation $invitation): bool
    {
        $cover = $invitation->cover_image;
        $disk = Storage::disk('public');

        if (! $cover || ! str_starts_with($cover, 'media/') || ! $disk->exists(substr($cover, 6))) {
            return false;
        }

        $bytes = $disk->get(substr($cover, 6));
        [$w, $h] = @getimagesizefromstring($bytes) ?: [0, 0];

        if (! $w || ! $h || $w * $h > 20_000_000 || ! ($source = @imagecreatefromstring($bytes))) {
            return false;
        }

        $scale = max(self::PHOTO_WIDTH / $w, self::HEIGHT / $h);
        $cropW = (int) round(self::PHOTO_WIDTH / $scale);
        $cropH = (int) round(self::HEIGHT / $scale);
        $x = intdiv($w - $cropW, 2);
        $y = (int) round(($h - $cropH) * 0.28);

        imagecopyresampled($img, $source, 0, 0, $x, $y, self::PHOTO_WIDTH, self::HEIGHT, $cropW, $cropH);

        return true;
    }

    /** The double gold border of the invitation, around the paper part. */
    private static function drawFrame(GdImage $img, int $left): void
    {
        $outer = self::color($img, self::GOLD_SOFT);
        $inner = self::color($img, [233, 220, 190]);

        imagesetthickness($img, 2);
        imagerectangle($img, $left + 22, 22, self::WIDTH - 23, self::HEIGHT - 23, $outer);
        imagesetthickness($img, 1);
        imagerectangle($img, $left + 32, 32, self::WIDTH - 33, self::HEIGHT - 33, $inner);
    }

    private static function drawOrnament(GdImage $img, float $cx, float $y, float $width): void
    {
        $color = self::color($img, self::GOLD_SOFT);
        imagesetthickness($img, 1);
        imageline($img, (int) ($cx - $width / 2), (int) $y, (int) ($cx - 14), (int) $y, $color);
        imageline($img, (int) ($cx + 14), (int) $y, (int) ($cx + $width / 2), (int) $y, $color);
        imagefilledpolygon($img, [(int) $cx, (int) $y - 6, (int) $cx + 6, (int) $y, (int) $cx, (int) $y + 6, (int) $cx - 6, (int) $y], $color);
    }

    /**
     * Draw text; every run of letters uses the font file that has them (Latin, Latin extended or Cyrillic).
     * Returns the width drawn.
     */
    private static function drawText(GdImage $img, string $text, string $style, float $size, array $rgb, float $x, float $baseline, float $spacing = 0, string $align = 'center'): float
    {
        $color = self::color($img, $rgb);
        $total = self::width($text, $style, $size, $spacing);
        $pen = $align === 'center' ? $x - $total / 2 : $x;

        foreach ($spacing > 0 ? array_map(fn ($char) => [$char], mb_str_split($text)) : [[$text]] as [$piece]) {
            foreach (self::runs($piece) as [$script, $run]) {
                imagettftext($img, $size, 0, (int) round($pen), (int) round($baseline), $color, self::font($style, $script), $run);
                $pen += self::advance($run, $style, $script, $size);
            }
            $pen += $spacing;
        }

        return $total;
    }

    // ------------------------------------------------------------------ text measuring

    private static function width(string $text, string $style, float $size, float $spacing = 0): float
    {
        $width = 0;

        foreach (self::runs($text) as [$script, $run]) {
            $width += self::advance($run, $style, $script, $size);
        }

        return $width + $spacing * max(0, mb_strlen($text) - 1);
    }

    /** How far the pen moves for this run. Measured between two marker letters, because a lone space has no ink. */
    private static function advance(string $run, string $style, string $script, float $size): float
    {
        static $cache = [];
        $key = "$style|$script|" . round($size, 1) . "|$run";

        if (! isset($cache[$key])) {
            $font = self::font($style, $script);
            $mark = ['latin' => 'H', 'latin-ext' => 'H', 'cyrillic' => 'Н'][$script];
            $with = imagettfbbox($size, 0, $font, $mark . $run . $mark);
            $without = imagettfbbox($size, 0, $font, $mark . $mark);
            $cache[$key] = ($with[2] - $with[0]) - ($without[2] - $without[0]);
        }

        return $cache[$key];
    }

    /**
     * Split into runs that each have one font file. Letters pick their font by alphabet; everything else
     * (spaces, digits, "&", commas) is in the Latin file and stays with the Latin font.
     *
     * @return array<int, array{0: string, 1: string}> [script, text]
     */
    private static function runs(string $text): array
    {
        $runs = [];

        foreach (mb_str_split($text) as $char) {
            $script = self::script($char);

            if ($runs && end($runs)[0] === $script) {
                $runs[array_key_last($runs)][1] .= $char;
            } else {
                $runs[] = [$script, $char];
            }
        }

        return $runs;
    }

    /** The same ranges the web font files are cut to (see public/css/invitation.css). */
    private static function script(string $char): string
    {
        $code = mb_ord($char);

        return match (true) {
            ($code >= 0x400 && $code <= 0x45F), $code === 0x301, $code === 0x2116, ($code >= 0x490 && $code <= 0x491), ($code >= 0x4B0 && $code <= 0x4B1) => 'cyrillic',
            $code <= 0xFF, ($code >= 0x2000 && $code <= 0x206F), in_array($code, [0x131, 0x152, 0x153, 0x2BB, 0x2BC, 0x2C6, 0x2DA, 0x2DC, 0x2074, 0x20AC, 0x2122, 0x2191, 0x2193, 0x2212, 0x2215], true) => 'latin',
            default => 'latin-ext',
        };
    }

    private static function font(string $style, string $script): string
    {
        return resource_path("fonts/cormorant-{$style}-{$script}.ttf");
    }

    /** The biggest size up to $max (and not below $min) at which the text fits the width. */
    private static function fit(string $text, string $style, float $max, float $width, float $min, bool $widest = false): float
    {
        $lines = $widest ? explode("\n", $text) : [$text];

        for ($size = $max; $size > $min; $size -= 2) {
            if (max(array_map(fn ($line) => self::width($line, $style, $size), $lines)) <= $width) {
                return $size;
            }
        }

        return $min;
    }

    /** Word-wrap into at most $maxLines lines; whatever does not fit ends in "…". */
    private static function wrap(string $text, string $style, float $size, float $width, int $maxLines): array
    {
        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $candidate = $line === '' ? $word : "$line $word";

            if ($line === '' || self::width($candidate, $style, $size) <= $width) {
                $line = $candidate;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }

        $lines[] = $line;

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], ' ,.;:') . '…';
        }

        return array_map(fn ($line) => self::ellipsize($line, $style, $size, $width), $lines);
    }

    /** A single word that is wider than the card is cut with "…". */
    private static function ellipsize(string $text, string $style, float $size, float $width): string
    {
        if (self::width($text, $style, $size) <= $width) {
            return $text;
        }

        while ($text !== '' && self::width($text . '…', $style, $size) > $width) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text) . '…';
    }

    private static function upperFirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    private static function color(GdImage $img, array $rgb): int
    {
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }
}
