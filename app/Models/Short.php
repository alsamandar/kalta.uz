<?php

namespace App\Models;

use App\Traits\Kaltaable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Short extends Model
{
    use HasFactory, Kaltaable;

    protected $guarded = [];
}
