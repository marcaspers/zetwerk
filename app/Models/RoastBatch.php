<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoastBatch extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['roasted_at' => 'date'];
}
