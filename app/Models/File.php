<?php

namespace App\Models;

use App\Traits\Kaltaable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded file. It is private: only people with its link can download it. New uploads live on the "local" disk
 * (storage/app/uploads, never served by the web server); older ones may still be on the "public" disk until
 * `php artisan files:make-private` moves them.
 */
class File extends Model
{
    use HasFactory, Kaltaable;

    public const DISK = 'local';

    protected $guarded = [];

    /** The disk that holds this file, or null when it is gone. */
    public function disk(): ?Filesystem
    {
        foreach ([self::DISK, 'public'] as $name) {
            if (Storage::disk($name)->exists($this->path)) {
                return Storage::disk($name);
            }
        }

        return null;
    }

    public function deleteStored(): void
    {
        foreach ([self::DISK, 'public'] as $name) {
            Storage::disk($name)->delete($this->path);
        }
    }
}
