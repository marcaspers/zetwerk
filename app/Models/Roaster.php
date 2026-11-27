<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roaster extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function coffees(): HasMany
    {
        return $this->hasMany(Coffee::class);
    }
}
