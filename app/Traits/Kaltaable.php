<?php

namespace App\Traits;

use App\Models\Kalta;

trait Kaltaable{
    public function kalta()
    {
        return $this->morphOne(Kalta::class, 'kaltaable');
    }
}