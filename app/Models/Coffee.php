<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coffee extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['notes' => 'array', 'roast_to_order' => 'boolean'];

    public function roaster(): BelongsTo
    {
        return $this->belongsTo(Roaster::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(CoffeeVariant::class)->orderBy('grams');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(RoastBatch::class)->orderByDesc('roasted_at');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class)->where('hidden', false);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('hidden', false);
    }

    public function maxRoastDays(): int
    {
        return min(config('platform.max_roast_days'), $this->max_roast_days ?? $this->roaster->max_roast_days, $this->roaster->max_roast_days);
    }

    public function availableBatch(): ?RoastBatch
    {
        return $this->batches()->where('stock', '>', 0)->whereDate('roasted_at', '>=', today()->subDays($this->maxRoastDays()))->whereDate('roasted_at', '<=', today())->first();
    }

    public function available(): bool
    {
        return $this->variants->sum('stock') > 0 && ($this->roast_to_order || $this->availableBatch() !== null);
    }
}
