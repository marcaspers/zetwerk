<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['payload' => 'array'];
}
