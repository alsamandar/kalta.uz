<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Kalta extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getRouteKeyName()
    {
        return 'url';
    }

    public function kaltaable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The public short address, on whichever host the site is served from. */
    public function getShortUrlAttribute(): string
    {
        return url($this->url);
    }

    /** short | file | bio | code | invitation */
    public function getTypeAttribute(): string
    {
        return Str::lower(class_basename($this->kaltaable_type));
    }
}
