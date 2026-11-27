<?php

namespace App\Livewire;

use App\Models\Coffee;
use App\Models\Roaster;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Catalog extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $roaster = '';

    #[Url]
    public string $country = '';

    #[Url]
    public string $method = '';

    #[Url]
    public string $process = '';

    #[Url]
    public string $roast = '';

    #[Url]
    public string $taste = '';

    #[Url]
    public string $sort = 'featured';

    public function resetFilters(): void
    {
        $this->reset('search', 'roaster', 'country', 'method', 'process', 'roast', 'taste', 'sort');
    }

    public function render(): View
    {
        $q = Coffee::with('roaster', 'variants')->withAvg('reviews', 'rating')->withCount('reviews');
        if ($this->search !== '') {
            $q->where(function ($q) {
                $q->whereLike('name', '%'.$this->search.'%')->orWhereLike('country', '%'.$this->search.'%')->orWhereHas('roaster', fn ($q) => $q->whereLike('name', '%'.$this->search.'%'));
            });
        }
        foreach (['roaster' => 'roaster_id', 'country' => 'country', 'process' => 'process', 'roast' => 'roast'] as $property => $column) {
            if ($this->$property !== '') {
                $q->where($column, $this->$property);
            }
        }
        if ($this->method !== '') {
            $q->whereIn('method', [$this->method, 'allround']);
        }
        if ($this->taste !== '') {
            $q->where('notes', 'like', '%'.$this->taste.'%');
        }
        if ($this->sort === 'name') {
            $q->orderBy('name');
        } elseif ($this->sort === 'rating') {
            $q->orderByDesc('reviews_avg_rating');
        } else {
            $q->orderBy('id');
        }

        return view('livewire.catalog', ['coffees' => $q->get(), 'roasters' => Roaster::all(), 'countries' => Coffee::distinct()->pluck('country')]);
    }
}
