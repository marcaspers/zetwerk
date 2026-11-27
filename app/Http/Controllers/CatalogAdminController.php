<?php

namespace App\Http\Controllers;

use App\Http\Requests\CoffeeRequest;
use App\Http\Requests\RoasterRequest;
use App\Models\Coffee;
use App\Models\CoffeeVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RoastBatch;
use App\Models\Roaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogAdminController extends Controller
{
    public function coffeeForm(?Coffee $coffee = null): View
    {
        Gate::authorize('admin');

        return view('platform.admin-coffee', ['coffee' => $coffee, 'roasters' => Roaster::all()]);
    }

    public function coffeeSave(CoffeeRequest $r, ?Coffee $coffee = null): RedirectResponse
    {
        $data = $r->safe()->except('notes_text');
        $data['notes'] = array_values(array_filter(array_map('trim', explode(',', $r->string('notes_text')))));
        if ($coffee) {
            if ($coffee->roaster_id != (int) $data['roaster_id']) {
                throw ValidationException::withMessages(['roaster_id' => 'Een bestaande koffie blijft gekoppeld aan dezelfde roaster. Maak een nieuw koffie-record.']);
            }
            $coffee->update($data);
        } else {
            $coffee = Coffee::create($data);
            foreach ([250, 1000] as $grams) {
                $coffee->variants()->create(['grams' => $grams, 'price_cents' => 1500 * ($grams === 250 ? 1 : 4), 'stock' => 0]);
            }
        }

        return to_route('admin')->with('success', 'Koffie opgeslagen. Een nieuw lot krijgt altijd een eigen record.');
    }

    public function roasterForm(?Roaster $roaster = null): View
    {
        Gate::authorize('admin');

        return view('platform.admin-roaster', compact('roaster'));
    }

    public function roasterSave(RoasterRequest $r, ?Roaster $roaster = null): RedirectResponse
    {
        if ($roaster) {
            $roaster->update($r->validated());
        } else {
            Roaster::create($r->validated());
        }

        return to_route('admin')->with('success', 'Roaster opgeslagen.');
    }

    public function assign(Request $r, Order $order, OrderItem $item): RedirectResponse
    {
        Gate::authorize('admin');
        abort_unless($item->order_id === $order->id, 404);
        $data = $r->validate(['batch_id' => 'required|exists:roast_batches,id']);
        DB::transaction(function () use ($order, $item, $data) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->payment_status === 'paid' && in_array($order->status, ['paid', 'sent_to_roaster', 'transfer_failed', 'accepted']), 422);
            $item = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $variant = CoffeeVariant::findOrFail($item->coffee_variant_id);
            $batch = RoastBatch::whereKey($data['batch_id'])->lockForUpdate()->firstOrFail();
            $max = min($item->batch_snapshot['max_roast_days'] ?? 14, config('platform.max_roast_days'));
            if ($batch->coffee_id !== $variant->coffee_id || $batch->roasted_at->lt(today()->subDays($max)) || $batch->roasted_at->gt(today()) || ($batch->id !== $item->roast_batch_id && $batch->stock < $item->quantity)) {
                throw ValidationException::withMessages(['batch' => 'Kies een verse batch van deze koffie met voldoende voorraad.']);
            }
            if ($batch->id !== $item->roast_batch_id) {
                $batch->decrement('stock', $item->quantity);
                if ($item->roast_batch_id) {
                    RoastBatch::whereKey($item->roast_batch_id)->increment('stock', $item->quantity);
                }
                $item->update(['roast_batch_id' => $batch->id]);
            }
        });

        return back()->with('success', 'Werkelijke batch toegewezen. De oorspronkelijke bestelbelofte is behouden.');
    }
}
