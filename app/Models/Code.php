<?php

namespace App\Models;

use App\Enums\ProgrammingLanguageEnum;
use App\Traits\Kaltaable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Code extends Model
{
    use HasFactory, Kaltaable, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'prog_lang' => ProgrammingLanguageEnum::class,
        'expiration' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expiration !== null && $this->expiration->isPast();
    }
}
