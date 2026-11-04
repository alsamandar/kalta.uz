<?php

namespace App\Support;

use GdImage;

/**
 * Lettering for the pictures chat apps show (see PreviewImage, PostPreview): Cormorant from resources/fonts, with every
 * run of letters drawn from the font file that has them, measuring, fitting and wrapping.
 */
trait GdText
{
    /**
     * Draw text; every run of letters uses the font file that has them (Latin, Latin extended or Cyrillic).
     * Returns the width drawn.
     */
    protected static function drawText(GdImage $img, string $text, string $style, float $size, array $rgb, float $x, float $baseline, float $spacing = 0, string $align = 'center'): float
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

    protected static function width(string $text, string $style, float $size, float $spacing = 0): float
    {
        $width = 0;

        foreach (self::runs($text) as [$script, $run]) {
            $width += self::advance($run, $style, $script, $size);
        }

        return $width + $spacing * max(0, mb_strlen($text) - 1);
    }

    /** How far the pen moves for this run. Measured between two marker letters, because a lone space has no ink. */
    protected static function advance(string $run, string $style, string $script, float $size): float
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
    protected static function runs(string $text): array
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
    protected static function script(string $char): string
    {
        $code = mb_ord($char);

        return match (true) {
            ($code >= 0x400 && $code <= 0x45F), $code === 0x301, $code === 0x2116, ($code >= 0x490 && $code <= 0x491), ($code >= 0x4B0 && $code <= 0x4B1) => 'cyrillic',
            $code <= 0xFF, ($code >= 0x2000 && $code <= 0x206F), in_array($code, [0x131, 0x152, 0x153, 0x2BB, 0x2BC, 0x2C6, 0x2DA, 0x2DC, 0x2074, 0x20AC, 0x2122, 0x2191, 0x2193, 0x2212, 0x2215], true) => 'latin',
            default => 'latin-ext',
        };
    }

    protected static function font(string $style, string $script): string
    {
        return resource_path("fonts/cormorant-{$style}-{$script}.ttf");
    }

    /** The biggest size up to $max (and not below $min) at which the text fits the width. */
    protected static function fit(string $text, string $style, float $max, float $width, float $min, bool $widest = false): float
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
    protected static function wrap(string $text, string $style, float $size, float $width, int $maxLines): array
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
    protected static function ellipsize(string $text, string $style, float $size, float $width): string
    {
        if (self::width($text, $style, $size) <= $width) {
            return $text;
        }

        while ($text !== '' && self::width($text . '…', $style, $size) > $width) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text) . '…';
    }

    protected static function upperFirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    protected static function color(GdImage $img, array $rgb): int
    {
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }
}
