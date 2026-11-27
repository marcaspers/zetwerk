<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CoffeeVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function cart(): Cart
    {
        session()->put('cart_key', session('cart_key', Str::uuid()->toString()));

        return Cart::firstOrCreate(['session_key' => session('cart_key')], ['items' => []]);
    }

    public function add(CoffeeVariant $variant, int $quantity, bool $replace = false): void
    {
        $cart = $this->cart();
        DB::transaction(function () use ($cart, $variant, $quantity, $replace) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $coffee = $variant->coffee;
            if ($cart->roaster_id && $cart->roaster_id !== $coffee->roaster_id && ! $replace) {
                throw ValidationException::withMessages(['roaster' => 'Je mand bevat koffie van een andere roaster. Kies bewust voor ‘Mand vervangen’ om verder te gaan.']);
            }
            $items = ($replace && $cart->roaster_id !== $coffee->roaster_id) ? [] : $cart->items;
            $quantity += ($items[$variant->id] ?? 0);
            if ($quantity < 1 || ! $coffee->available() || $quantity > $variant->stock) {
                throw ValidationException::withMessages(['stock' => 'Deze variant is uitverkocht of er is geen batch binnen het versheidsbeleid beschikbaar.']);
            }
            $items[$variant->id] = $quantity;
            $cart->update(['roaster_id' => $coffee->roaster_id, 'items' => $items]);
        }, 3);
        session()->forget('checkout_key');
    }

    public function lines(): Collection
    {
        $items = $this->cart()->items;

        return CoffeeVariant::with('coffee.roaster')->whereIn('id', array_keys($items))->orderBy('id')->get()->map(fn ($v) => ['variant' => $v, 'quantity' => $items[$v->id], 'total' => $v->price_cents * $items[$v->id]]);
    }

    public function update(int $id, int $quantity): void
    {
        $cart = $this->cart();
        DB::transaction(function () use ($cart, $id, $quantity) {
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $items = $cart->items;
            if ($quantity === 0) {
                unset($items[$id]);
            } else {
                $variant = CoffeeVariant::findOrFail($id);
                if (! isset($items[$id]) || $quantity < 0 || $quantity > $variant->stock) {
                    throw ValidationException::withMessages(['stock' => 'Niet voldoende voorraad.']);
                }
                $items[$id] = $quantity;
            }
            $cart->update(['items' => $items, 'roaster_id' => count($items) ? $cart->roaster_id : null]);
        }, 3);
        session()->forget('checkout_key');
    }
}
