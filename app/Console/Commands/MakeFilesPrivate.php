<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Older uploads were kept on the "public" disk, which the web server can serve straight from /storage. This moves
 * them to the private disk; their short links keep working because KaltaController looks on both.
 */
class MakeFilesPrivate extends Command
{
    protected $signature = 'files:make-private {--dry-run : Only count what would move}';

    protected $description = 'Move uploaded files from the public disk to the private one';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk(File::DISK);
        $moved = $failed = 0;

        File::query()->orderBy('id')->chunkById(200, function ($files) use ($public, $private, &$moved, &$failed) {
            foreach ($files as $file) {
                if (! $public->exists($file->path) || $private->exists($file->path)) {
                    continue;
                }

                if ($this->option('dry-run')) {
                    $moved++;
                    continue;
                }

                $stream = $public->readStream($file->path);
                $ok = $stream && $private->writeStream($file->path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($ok && $private->size($file->path) === $public->size($file->path)) {
                    $public->delete($file->path);
                    $moved++;
                } else {
                    $private->delete($file->path);
                    $failed++;
                    $this->warn("Could not move {$file->path}");
                }
            }
        });

        $this->info(($this->option('dry-run') ? 'Would move' : 'Moved') . " $moved file(s)" . ($failed ? ", $failed failed" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
