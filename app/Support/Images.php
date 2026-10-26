<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns uploaded photos into small web-ready JPEGs on the "public" disk and
 * returns the path to store (served by MediaController under /media/...).
 * Phone photos are several MB; pages show them at a few hundred pixels.
 * If a file cannot be decoded (or is too big to decode inside memory_limit)
 * the validated original is stored under a random name instead.
 */
class Images
{
    /** Square, centre-cropped profile picture. */
    public static function avatar(UploadedFile $file, int $size = 512): string
    {
        return self::process($file, 'avatars', $size, $size, true);
    }

    /** Wide cover photo, keeps its proportions. */
    public static function cover(UploadedFile $file, int $maxWidth = 1600): string
    {
        return self::process($file, 'covers', $maxWidth, null, false);
    }

    private static function process(UploadedFile $file, string $folder, int $width, ?int $height, bool $square): string
    {
        $disk = Storage::disk('public');
        $name = time() . '_' . Str::random(16);
        $path = $file->getRealPath();
        [$w, $h] = @getimagesize($path) ?: [0, 0];

        // GD needs ~4 bytes per pixel; stay well inside a 128M memory_limit
        $source = ($w && $h && $w * $h <= 20_000_000)
            ? @imagecreatefromstring((string) file_get_contents($path))
            : false;

        if ($source === false) {
            $disk->putFileAs($folder, $file, "$name." . $file->extension());

            return "media/$folder/$name." . $file->extension();
        }

        $orientation = 1;
        if (function_exists('exif_read_data') && in_array($file->extension(), ['jpg', 'jpeg'], true)) {
            $orientation = @exif_read_data($path)['Orientation'] ?? 1;
        }

        $source = match ($orientation) {
            3 => imagerotate($source, 180, 0),
            6 => imagerotate($source, -90, 0),
            8 => imagerotate($source, 90, 0),
            default => $source,
        };

        $w = imagesx($source);
        $h = imagesy($source);

        if ($square) {
            $side = min($w, $h);
            [$sx, $sy, $sw, $sh] = [intdiv($w - $side, 2), intdiv($h - $side, 2), $side, $side];
            [$dw, $dh] = [$width, $height];
        } else {
            [$sx, $sy, $sw, $sh] = [0, 0, $w, $h];
            $dw = min($width, $w); // never upscale
            $dh = (int) round($h * ($dw / $w));
        }

        $canvas = imagecreatetruecolor($dw, $dh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); // transparent PNGs end up on white
        imagecopyresampled($canvas, $source, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);

        ob_start();
        imagejpeg($canvas, null, 82);
        $disk->put("$folder/$name.jpg", ob_get_clean());

        return "media/$folder/$name.jpg";
    }
}
