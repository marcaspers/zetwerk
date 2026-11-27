<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['hidden' => 'boolean', 'roasted_at' => 'date'];

    public function coffee(): BelongsTo
    {
        return $this->belongsTo(Coffee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'recipe_votes');
    }

    public function ratio(): string
    {
        return '1:'.rtrim(rtrim(number_format($this->water / $this->dose, 1, '.', ''), '0'), '.');
    }
}
