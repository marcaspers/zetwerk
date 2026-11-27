<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\RoasterGateway;
use App\Models\Cart;
use App\Models\CoffeeVariant;
use App\Models\IntegrationEvent;
use App\Models\Order;
use App\Models\RoastBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private PaymentGateway $payments, private RoasterGateway $roasters) {}

    public function place(User $user, Cart $cart, array $address, string $key): Order
    {
        return DB::transaction(function () use ($user, $cart, $address, $key) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($existing = Order::where('checkout_key', $key)->first()) {
                abort_unless($existing->user_id === $user->id, 403);

                return $existing;
            }
            $cart = Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            if (! $cart->items) {
                throw ValidationException::withMessages(['cart' => 'Je mand is nog leeg.']);
            }
            $variants = CoffeeVariant::with('coffee.roaster')->whereIn('id', array_keys($cart->items))->orderBy('id')->lockForUpdate()->get();
            $lines = [];
            $subtotal = 0;
            foreach ($variants as $variant) {
                $quantity = $cart->items[$variant->id];
                $coffee = $variant->coffee;
                if ($quantity < 1 || $quantity > $variant->stock || $coffee->roaster_id !== $cart->roaster_id) {
                    throw ValidationException::withMessages(['stock' => 'De voorraad is gewijzigd. Controleer je mand.']);
                }
                $batch = null;
                if (! $coffee->roast_to_order) {
                    $batch = $coffee->batches()->where('stock', '>=', $quantity)->whereDate('roasted_at', '>=', today()->subDays($coffee->maxRoastDays()))->whereDate('roasted_at', '<=', today())->lockForUpdate()->first();
                    if (! $batch) {
                        throw ValidationException::withMessages(['batch' => 'Geen geschikte brandbatch beschikbaar. Deze koffie kan nu niet worden besteld.']);
                    }
                    $batch->decrement('stock', $quantity);
                }
                $variant->decrement('stock', $quantity);
                $subtotal += $quantity * $variant->price_cents;
                $lines[] = ['coffee_variant_id' => $variant->id, 'roast_batch_id' => $batch?->id, 'name' => $coffee->name, 'grams' => $variant->grams, 'quantity' => $quantity, 'price_cents' => $variant->price_cents, 'batch_snapshot' => $batch ? ['code' => $batch->code, 'roasted_at' => $batch->roasted_at->toDateString(), 'max_roast_days' => $coffee->maxRoastDays()] : ['status' => 'Nog onbekend — wordt na bestelling gebrand', 'max_roast_days' => $coffee->maxRoastDays()]];
            }
            if (! $lines) {
                throw ValidationException::withMessages(['cart' => 'Geen geldige artikelen.']);
            }
            $roaster = $variants->first()->coffee->roaster;
            $order = Order::create(['user_id' => $user->id, 'roaster_id' => $roaster->id, 'checkout_key' => $key, 'number' => 'ZW-'.strtoupper(substr(str_replace('-', '', $key), 0, 10)), 'subtotal_cents' => $subtotal, 'shipping_cents' => $roaster->shipping_cents, 'total_cents' => $subtotal + $roaster->shipping_cents, 'address' => $address, 'promise' => ['roaster' => $roaster->name, 'shipping' => $roaster->shipping_policy, 'max_roast_days' => $roaster->max_roast_days, 'platform_max_days' => config('platform.max_roast_days'), 'demo' => true], 'reserved_until' => now()->addMinutes(config('platform.reservation_minutes'))]);
            $order->items()->createMany($lines);
            $order->payment()->create(['status' => 'pending', 'amount_cents' => $order->total_cents]);
            $cart->update(['items' => [], 'roaster_id' => null]);

            return $order->fresh();
        }, 3);
    }

    public function pay(Order $order, string $scenario, string $event): Order
    {
        return DB::transaction(function () use ($order, $scenario, $event) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (IntegrationEvent::where('key', $event)->exists()) {
                return $order;
            }
            if ($order->payment_status !== 'pending') {
                return $order;
            }
            if ($order->reserved_until->isPast()) {
                $this->release($order, 'expired');

                return $order->fresh();
            }
            $status = $this->payments->status($order, $scenario);
            IntegrationEvent::create(['key' => $event, 'type' => 'demo.payment', 'payload' => ['order_id' => $order->id, 'status' => $status]]);
            $order->payment()->update(['status' => $status]);
            $order->update(['payment_status' => $status, 'status' => $status === 'paid' ? 'paid' : 'awaiting_payment']);
            if (in_array($status, ['failed', 'cancelled'])) {
                $this->release($order, $status);
            }

            return $order->fresh();
        }, 3);
    }

    private function release(Order $order, string $reason): void
    {
        if ($order->released_at || $order->payment_status === 'paid') {
            return;
        }
        foreach ($order->items()->orderBy('coffee_variant_id')->get() as $item) {
            CoffeeVariant::whereKey($item->coffee_variant_id)->increment('stock', $item->quantity);
            if ($item->roast_batch_id) {
                RoastBatch::whereKey($item->roast_batch_id)->increment('stock', $item->quantity);
            }
        }
        $order->update(['released_at' => now(), 'payment_status' => $reason, 'status' => 'cancelled']);
        $order->payment()->update(['status' => $reason]);
    }

    public function expire(): int
    {
        $count = 0;
        foreach (Order::where('payment_status', 'pending')->where('reserved_until', '<', now())->pluck('id') as $id) {
            DB::transaction(function () use ($id) {
                $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($order->payment_status === 'pending') {
                    $this->release($order, 'expired');
                }
            });
            $count++;
        }

        return $count;
    }

    public function fulfill(Order $order, string $action, bool $fail = false): void
    {
        DB::transaction(function () use ($order, $action, $fail) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->payment_status !== 'paid') {
                throw ValidationException::withMessages(['payment' => 'Alleen betaalde bestellingen mogen naar de roaster.']);
            }
            if ($action === 'transfer' && in_array($order->status, ['paid', 'transfer_failed'])) {
                try {
                    $this->roasters->transfer($order, $fail);
                    $order->update(['status' => 'sent_to_roaster']);
                } catch (\RuntimeException $e) {
                    $order->update(['status' => 'transfer_failed']);
                }
            }
            if ($action === 'accept' && $order->status === 'sent_to_roaster') {
                $order->update(['status' => 'accepted', 'shipment_status' => 'preparing']);
                $order->shipment()->firstOrCreate([], ['status' => 'preparing']);
            }
            if ($action === 'ship' && $order->status === 'accepted') {
                foreach ($order->items as $item) {
                    if ($item->roast_batch_id) {
                        $assigned = RoastBatch::findOrFail($item->roast_batch_id);
                        if ($assigned->roasted_at->lt(today()->subDays(min($item->batch_snapshot['max_roast_days'] ?? 14, config('platform.max_roast_days'))))) {
                            throw ValidationException::withMessages(['batch' => 'De toegewezen batch is nu te oud om te verzenden. Wijs eerst een verse vervangende batch toe.']);
                        }
                    }
                    if (! $item->roast_batch_id) {
                        $batch = RoastBatch::create(['coffee_id' => CoffeeVariant::findOrFail($item->coffee_variant_id)->coffee_id, 'code' => 'DEMO-'.$order->id.'-'.$item->id, 'roasted_at' => today(), 'stock' => 0]);
                        $item->update(['roast_batch_id' => $batch->id]);
                    }
                }
                $order->update(['status' => 'shipped', 'shipment_status' => 'shipped']);
                $order->shipment()->update(['status' => 'shipped', 'tracking' => 'DEMO-ZW-'.$order->number]);
            }
            if ($action === 'deliver' && $order->status === 'shipped') {
                $order->update(['status' => 'delivered', 'shipment_status' => 'delivered']);
                $order->shipment()->update(['status' => 'delivered']);
            }
        });
    }
}
