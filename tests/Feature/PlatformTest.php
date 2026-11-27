<?php

namespace Tests\Feature;

use App\Livewire\Catalog;
use App\Models\Cart;
use App\Models\Coffee;
use App\Models\CoffeeVariant;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\Review;
use App\Models\RoastBatch;
use App\Models\Roaster;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function order(int $coffee = 1, int $quantity = 1, ?User $user = null): Order
    {
        $v = CoffeeVariant::where('coffee_id', $coffee)->where('grams', 250)->firstOrFail();
        $cart = Cart::create(['session_key' => Str::uuid()->toString(), 'roaster_id' => $v->coffee->roaster_id, 'items' => [$v->id => $quantity]]);

        return app(OrderService::class)->place($user ?? User::first(), $cart, ['name' => 'Test Gebruiker', 'street' => 'Teststraat 1', 'postcode' => '1234 AB', 'city' => 'Utrecht'], Str::uuid()->toString());
    }

    public function test_public_pages_and_seeded_edge_cases_are_available(): void
    {
        foreach (['/', '/koffies', '/roasters', '/roasters/bosrand', '/recepten', '/recepten/1', '/login', '/register', '/mand'] as $url) {
            $this->get($url)->assertOk();
        }foreach (Coffee::all() as $coffee) {
            $this->get(route('coffee', $coffee))->assertOk()->assertSee($coffee->name);
        }
        $this->assertDatabaseCount('roasters', 3);
        $this->assertDatabaseCount('coffees', 6);
        $this->assertDatabaseCount('recipes', 12);
        $this->assertDatabaseCount('reviews', 18);
        $this->assertDatabaseCount('users', 8);
        $this->get('/koffies/decaf-lot-06')->assertSee('Nog geen reviews')->assertSee('Tijdelijk uitverkocht');
    }

    public function test_catalog_filters_search_and_empty_state(): void
    {
        Livewire::test(Catalog::class)->set('country', 'Ethiopië')->assertSee('Hoogland Lot 01')->assertDontSee('Bergtuin Lot 02')->set('search', 'onvindbaar')->assertSee('Deze smaak hebben we nog niet.')->call('resetFilters')->set('method', 'espresso')->assertSee('Bergtuin Lot 02')->assertDontSee('Hoogland Lot 01')->set('method', '')->set('search', 'bosrand')->assertSee('Hoogland Lot 01')->assertSee('Bergtuin Lot 02')->assertDontSee('Vallei Lot 03');
    }

    public function test_login_preserves_cart_and_registration_does_not_grant_admin(): void
    {
        $this->get('/');
        $cart = app(CartService::class);
        $cart->add(CoffeeVariant::orderBy('id')->first(), 1);
        $key = session('cart_key');
        Volt::test('auth.login')->set('email', 'noor@zetwerk.test')->set('password', 'koffietijd')->call('login')->assertHasNoErrors();
        $this->assertAuthenticated();
        $this->assertSame($key, session('cart_key'));
        $this->assertCount(1, $cart->cart()->items);
        auth()->logout();
        Volt::test('auth.register')->set('name', 'Test Liefhebber')->set('email', 'test@zetwerk.test')->set('password', 'testwachtwoord123')->set('password_confirmation', 'testwachtwoord123')->call('register')->assertHasNoErrors();
        $this->assertFalse(User::where('email', 'test@zetwerk.test')->first()->is_admin);
    }

    public function test_a_second_roaster_never_silently_replaces_the_cart(): void
    {
        $this->get('/');
        $cart = app(CartService::class);
        $cart->add(CoffeeVariant::where('coffee_id', 1)->first(), 1);
        try {
            $cart->add(CoffeeVariant::where('coffee_id', 3)->first(), 1);
            $this->fail('Expected a clear conflict.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('roaster', $e->errors());
        }
        $this->assertSame(1, $cart->cart()->roaster_id);
        $cart->add(CoffeeVariant::where('coffee_id', 3)->first(), 1, true);
        $this->assertSame(2, $cart->cart()->roaster_id);
        $this->assertCount(1, $cart->cart()->items);
    }

    public function test_stale_batches_and_sold_out_variants_are_blocked_without_reserving_stock(): void
    {
        foreach ([4, 6] as $id) {
            try {
                $this->order($id);
                $this->fail('Unavailable coffee was accepted.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(24, CoffeeVariant::where('coffee_id', 4)->first()->stock);
    }

    public function test_totals_snapshots_and_duplicate_checkout_are_server_controlled(): void
    {
        $v = CoffeeVariant::orderBy('id')->first();
        $cart = Cart::create(['session_key' => 'idempotent', 'roaster_id' => 1, 'items' => [$v->id => 2]]);
        $key = Str::uuid()->toString();
        $service = app(OrderService::class);
        $address = ['name' => 'Test', 'street' => 'Test 1', 'postcode' => '1234 AB', 'city' => 'Utrecht'];
        $order = $service->place(User::first(), $cart, $address, $key);
        $same = $service->place(User::first(), $cart, $address, $key);
        $this->assertSame($order->id, $same->id);
        $this->assertSame(3300, $order->subtotal_cents);
        $this->assertSame(3795, $order->total_cents);
        $this->assertSame('EUR', $order->currency);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(22, $v->fresh()->stock);
        $this->assertNotNull($order->items->first()->roast_batch_id);
        $v->update(['price_cents' => 9999]);
        $this->assertSame(1650, $order->items->first()->price_cents);
    }

    public function test_failed_and_cancelled_payments_release_stock_once_and_cannot_fulfill(): void
    {
        foreach (['failed', 'cancelled'] as $status) {
            $order = $this->order();
            $s = app(OrderService::class);
            $s->pay($order, $status, 'event-'.$status);
            $s->pay($order, $status, 'event-'.$status);
            $this->assertSame(24, CoffeeVariant::orderBy('id')->first()->stock);
            $this->assertSame($status, $order->fresh()->payment_status);
            try {
                $s->fulfill($order, 'transfer');
                $this->fail('Unpaid fulfillment allowed');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('payment', $e->errors());
            }
        }
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_pending_payment_expiry_and_late_success_never_start_fulfillment(): void
    {
        $order = $this->order();
        $s = app(OrderService::class);
        $s->pay($order, 'pending', 'pending-event');
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->travel(16)->minutes();
        $s->pay($order, 'paid', 'late-event');
        $this->assertSame('expired', $order->fresh()->payment_status);
        $this->assertSame(24, CoffeeVariant::orderBy('id')->first()->stock);
        $s->expire();
        $this->assertSame(24, CoffeeVariant::orderBy('id')->first()->stock);
    }

    public function test_paid_flow_transfer_retry_and_replayed_events_are_idempotent(): void
    {
        $order = $this->order();
        $s = app(OrderService::class);
        $s->pay($order, 'paid', 'paid-event');
        $s->pay($order, 'paid', 'paid-event');
        $s->pay($order, 'failed', 'later-failure');
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('integration_events', 1);
        $s->fulfill($order, 'transfer', true);
        $this->assertSame('transfer_failed', $order->fresh()->status);
        $s->fulfill($order, 'transfer');
        $s->fulfill($order, 'transfer');
        $s->fulfill($order, 'accept');
        $s->fulfill($order, 'accept');
        $s->fulfill($order, 'ship');
        $s->fulfill($order, 'ship');
        $s->fulfill($order, 'deliver');
        $this->assertSame('delivered', $order->fresh()->shipment_status);
        $this->assertDatabaseCount('shipments', 1);
        $this->assertStringStartsWith('DEMO-', Shipment::first()->tracking);
        $this->assertSame(23, CoffeeVariant::orderBy('id')->first()->stock);
    }

    public function test_roast_to_order_assigns_actual_batch_without_overwriting_original_promise(): void
    {
        $order = $this->order(5);
        $item = $order->items->first();
        $snapshot = $item->batch_snapshot;
        $this->assertNull($item->roast_batch_id);
        $s = app(OrderService::class);
        $s->pay($order, 'paid', 'rto-payment');
        foreach (['transfer', 'accept', 'ship'] as $action) {
            $s->fulfill($order, $action);
        }
        $this->assertNotNull($item->fresh()->roast_batch_id);
        $this->assertSame($snapshot, $item->fresh()->batch_snapshot);
    }

    public function test_shipping_rechecks_batch_age_and_admin_can_replace_it(): void
    {
        $order = $this->order();
        $s = app(OrderService::class);
        $s->pay($order, 'paid', 'ship-age');
        $s->fulfill($order, 'transfer');
        $s->fulfill($order, 'accept');
        $this->travel(15)->days();
        try {
            $s->fulfill($order, 'ship');
            $this->fail('Stale shipment accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('batch', $e->errors());
        }
        $item = $order->items->first();
        $snapshot = $item->batch_snapshot;
        $batch = RoastBatch::create(['coffee_id' => 1, 'code' => 'replacement', 'roasted_at' => today(), 'stock' => 3]);
        $this->actingAs(User::where('is_admin', true)->first())->post(route('admin.assign', [$order, $item]), ['batch_id' => $batch->id])->assertRedirect();
        $s->fulfill($order, 'ship');
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame($snapshot, $item->fresh()->batch_snapshot);
    }

    public function test_last_stock_is_not_oversold_and_reservation_expiry_is_repeatable(): void
    {
        $variant = CoffeeVariant::orderBy('id')->first();
        $variant->update(['stock' => 1]);
        $this->order();
        try {
            $this->order();
            $this->fail('Oversold last bag.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $this->assertSame(0, $variant->fresh()->stock);
        $this->travel(16)->minutes();
        $s = app(OrderService::class);
        $this->assertSame(1, $s->expire());
        $this->assertSame(0, $s->expire());
        $this->assertSame(1, $variant->fresh()->stock);
    }

    public function test_recipe_authorization_unique_reviews_favorites_and_moderation(): void
    {
        $user = User::find(2);
        $recipe = Recipe::first();
        $this->actingAs($user)->put(route('recipe.update', $recipe), [])->assertForbidden();
        $this->delete(route('recipe.delete', $recipe))->assertForbidden();
        $this->get('/beheer')->assertForbidden();
        $this->patch(route('admin.inventory', CoffeeVariant::orderBy('id')->first()), ['stock' => 10, 'price_cents' => 100])->assertForbidden();
        $data = ['rating' => 3, 'method' => 'Espresso', 'body' => 'Een prima kop koffie met een mooie zoetheid.'];
        $this->post(route('review.store', Coffee::first()), $data)->assertRedirect();
        $this->post(route('review.store', Coffee::first()), $data + ['user_id' => 8])->assertRedirect();
        $this->assertSame(1, Review::where('coffee_id', 1)->where('user_id', 2)->count());
        $this->post(route('favorite'), ['kind' => 'coffee', 'target_id' => 1])->assertRedirect();
        $this->assertDatabaseCount('favorites', 1);
        $this->get('/bewaard')->assertOk()->assertSee('Hoogland Lot 01');
        $this->post(route('report'), ['kind' => 'recipe', 'target_id' => $recipe->id, 'reason' => 'Ongepaste inhoud in voorbeeld'])->assertRedirect();
        $this->actingAs(User::where('is_admin', true)->first())->post(route('admin.moderate'), ['kind' => 'recipe', 'target_id' => $recipe->id, 'reason' => 'Verborgen na melding'])->assertRedirect();
        $this->get(route('recipe', $recipe))->assertNotFound();
        $this->assertDatabaseHas('reports', ['status' => 'resolved']);
        $this->get('/beheer')->assertOk()->assertSee('Verborgen na melding');
    }

    public function test_an_owner_can_publish_edit_and_delete_a_recipe(): void
    {
        $this->actingAs(User::first());
        $data = ['coffee_id' => 1, 'title' => 'Mijn testrecept', 'method' => 'V60', 'dose' => 15, 'water' => 250, 'temperature' => 94, 'seconds' => 180, 'grinder' => 'Comandante C40', 'grind' => '24 kliks', 'steps' => '1. Spoel het filter. 2. Schenk rustig op tot 250 gram.'];
        $this->post(route('recipe.store'), $data)->assertRedirect();
        $recipe = Recipe::where('title', 'Mijn testrecept')->firstOrFail();
        $this->assertSame('1:16.7', $recipe->ratio());
        $this->get(route('recipe.edit', $recipe))->assertOk();
        $data['title'] = 'Bijgesteld testrecept';
        $this->put(route('recipe.update', $recipe), $data)->assertRedirect();
        $this->assertSame('Bijgesteld testrecept', $recipe->fresh()->title);
        $this->delete(route('recipe.delete', $recipe))->assertRedirect();
        $this->assertModelMissing($recipe);
    }

    public function test_order_access_checkout_address_validation_and_later_order_key(): void
    {
        $order = $this->order();
        $this->actingAs(User::find(2))->get(route('order', $order))->assertForbidden();
        $this->post(route('order.pay', $order), ['scenario' => 'paid', 'event' => Str::uuid()->toString()])->assertForbidden();
        $this->actingAs(User::first());
        $this->post(route('cart.add'), ['variant_id' => 1, 'quantity' => 1])->assertRedirect();
        $this->get(route('checkout'))->assertOk();
        $key = session('checkout_key');
        $this->post(route('checkout.place'), ['checkout_key' => $key, 'name' => 'Test', 'street' => 'Test 1', 'postcode' => 'fout', 'city' => 'Utrecht'])->assertSessionHasErrors('postcode');
        $data = ['checkout_key' => $key, 'name' => 'Test', 'street' => 'Test 1', 'postcode' => '1234 AB', 'city' => 'Utrecht', 'total_cents' => 1];
        $this->post(route('checkout.place'), $data)->assertRedirect();
        $this->post(route('checkout.place'), $data)->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
        $this->post(route('cart.add'), ['variant_id' => 1, 'quantity' => 1]);
        $this->get(route('checkout'));
        $this->assertNotSame($key, session('checkout_key'));
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('order', Order::latest('id')->first()))->assertOk()->assertSee('Kies een demo-scenario.');
    }

    public function test_admin_catalog_management_and_product_policy(): void
    {
        $this->actingAs(User::where('is_admin', true)->first());
        $this->get(route('admin.coffee.create'))->assertOk();
        $this->get(route('admin.coffee.edit', Coffee::first()))->assertOk();
        $this->get(route('admin.roaster.create'))->assertOk();
        $this->get(route('admin.roaster.edit', Roaster::first()))->assertOk();
        $coffee = Coffee::first();
        $data = $coffee->only(['roaster_id', 'name', 'slug', 'country', 'region', 'producer', 'variety', 'process', 'roast', 'method', 'description', 'color', 'roast_to_order']);
        $data['notes_text'] = 'Perzik, Jasmijn';
        $data['max_roast_days'] = 2;
        $this->put(route('admin.coffee.update', $coffee), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($coffee->fresh()->available());
        $this->get('/beheer')->assertOk();
    }
}
