<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Coffee;
use App\Models\CoffeeVariant;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\Recipe;
use App\Models\Report;
use App\Models\Review;
use App\Models\RoastBatch;
use App\Models\Roaster;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function home(): View
    {
        return view('platform.home', ['coffees' => Coffee::with('roaster', 'variants')->withAvg('reviews', 'rating')->withCount('reviews')->limit(3)->get(), 'recipes' => Recipe::where('hidden', false)->with('coffee', 'user')->limit(3)->get(), 'roasters' => Roaster::all()]);
    }

    public function catalog(): View
    {
        return view('platform.catalog');
    }

    public function coffee(Coffee $coffee): View
    {
        $coffee->load('roaster', 'variants', 'batches', 'recipes.user', 'recipes.voters', 'reviews.user');

        return view('platform.coffee', compact('coffee'));
    }

    public function roasters(): View
    {
        return view('platform.roasters', ['roasters' => Roaster::withCount('coffees')->get()]);
    }

    public function roaster(Roaster $roaster): View
    {
        return view('platform.roaster', ['roaster' => $roaster, 'coffees' => $roaster->coffees()->with('roaster', 'variants')->withAvg('reviews', 'rating')->withCount('reviews')->get()]);
    }

    public function recipes(Request $r): View
    {
        $q = Recipe::where('hidden', false)->with('coffee', 'user')->withCount('voters');
        if ($r->filled('method')) {
            $q->where('method', $r->string('method')->toString());
        }
        $recipes = $r->get('sort') === 'helpful' ? $q->orderByDesc('voters_count')->get() : $q->latest()->get();

        return view('platform.recipes', compact('recipes'));
    }

    public function recipe(Recipe $recipe): View
    {
        abort_if($recipe->hidden, 404);

        return view('platform.recipe', ['recipe' => $recipe->load('coffee.roaster', 'user', 'voters')]);
    }

    public function recipeForm(Request $r, ?Recipe $recipe = null): View
    {
        if ($recipe) {
            Gate::authorize('update', $recipe);
        }

        return view('platform.recipe-form', ['recipe' => $recipe, 'coffees' => Coffee::all(), 'selected' => $r->get('coffee')]);
    }

    public function saveRecipe(Request $r, ?Recipe $recipe = null): RedirectResponse
    {
        if ($recipe) {
            Gate::authorize('update', $recipe);
        }
        $data = $r->validate(['coffee_id' => 'required|exists:coffees,id', 'title' => 'required|string|max:120', 'method' => 'required|in:V60,Espresso,Aeropress,Chemex,French press', 'dose' => 'required|numeric|min:1|max:100', 'water' => 'required|numeric|min:1|max:2000', 'temperature' => 'required|integer|min:60|max:100', 'seconds' => 'required|integer|min:10|max:1200', 'grinder' => 'required|string|max:100', 'grind' => 'required|string|max:100', 'machine' => 'nullable|string|max:100', 'bloom' => 'nullable|integer|min:0|max:500', 'roasted_at' => 'nullable|date|before_or_equal:today', 'steps' => 'required|string|min:20|max:5000']);
        if ($recipe) {
            $recipe->update($data);
        } else {
            $recipe = Recipe::create($data + ['user_id' => $r->user()->id]);
        }

        return to_route('recipe', $recipe)->with('success', 'Je recept is opgeslagen. Tijd om te zetten!');
    }

    public function deleteRecipe(Recipe $recipe): RedirectResponse
    {
        Gate::authorize('delete', $recipe);
        $recipe->delete();

        return to_route('recipes')->with('success', 'Recept verwijderd.');
    }

    public function vote(Request $r, Recipe $recipe): RedirectResponse
    {
        abort_if($recipe->hidden, 404);
        $recipe->voters()->toggle($r->user()->id);

        return back()->with('success', 'Je waardering is bijgewerkt.');
    }

    public function review(Request $r, Coffee $coffee): RedirectResponse
    {
        $data = $r->validate(['rating' => 'required|integer|between:1,5', 'method' => 'required|in:V60,Espresso,Aeropress,Chemex,French press', 'body' => 'required|string|min:10|max:2000']);
        $review = Review::where('user_id', $r->user()->id)->where('coffee_id', $coffee->id)->first();
        if ($review) {
            Gate::authorize('update', $review);
            $review->update($data);
        } else {
            Review::create($data + ['user_id' => $r->user()->id, 'coffee_id' => $coffee->id]);
        }

        return back()->with('success', 'Je koffie-ervaring is opgeslagen.');
    }

    public function deleteReview(Review $review): RedirectResponse
    {
        Gate::authorize('delete', $review);
        $review->delete();

        return back()->with('success', 'Review verwijderd.');
    }

    public function favorite(Request $r): RedirectResponse
    {
        $data = $r->validate(['kind' => 'required|in:coffee,recipe', 'target_id' => 'required|integer']);
        ($data['kind'] === 'coffee' ? Coffee::class : Recipe::class)::findOrFail($data['target_id']);
        $q = Favorite::where($data + ['user_id' => $r->user()->id]);
        if ($q->exists()) {
            $q->delete();
        } else {
            Favorite::create($data + ['user_id' => $r->user()->id]);
        }

        return back()->with('success', 'Je bewaarde lijst is bijgewerkt.');
    }

    public function saved(Request $r): View
    {
        $favorites = Favorite::where('user_id', $r->user()->id)->get();

        return view('platform.saved', ['coffees' => Coffee::with('roaster', 'variants')->withAvg('reviews', 'rating')->withCount('reviews')->whereIn('id', $favorites->where('kind', 'coffee')->pluck('target_id'))->get(), 'recipes' => Recipe::where('hidden', false)->with('coffee', 'user')->whereIn('id', $favorites->where('kind', 'recipe')->pluck('target_id'))->get()]);
    }

    public function cart(CartService $cart): View
    {
        return view('platform.cart', ['lines' => $cart->lines()]);
    }

    public function add(Request $r, CartService $cart): RedirectResponse
    {
        $data = $r->validate(['variant_id' => 'required|exists:coffee_variants,id', 'quantity' => 'required|integer|min:1|max:20', 'replace' => 'nullable|boolean']);
        $cart->add(CoffeeVariant::findOrFail($data['variant_id']), $data['quantity'], (bool) ($data['replace'] ?? false));

        return to_route('cart')->with('success', 'Koffie toegevoegd aan je mand.');
    }

    public function updateCart(Request $r, CartService $cart): RedirectResponse
    {
        $data = $r->validate(['variant_id' => 'required|integer', 'quantity' => 'required|integer|min:0|max:20']);
        $cart->update($data['variant_id'], $data['quantity']);

        return back();
    }

    public function checkout(CartService $cart, OrderService $orders): View|RedirectResponse
    {
        $orders->expire();
        $lines = $cart->lines();
        if ($lines->isEmpty()) {
            return to_route('cart');
        }session()->put('checkout_key', session('checkout_key', Str::uuid()->toString()));

        return view('platform.checkout', ['lines' => $lines, 'key' => session('checkout_key')]);
    }

    public function place(CheckoutRequest $r, CartService $cart, OrderService $orders): RedirectResponse
    {
        abort_unless(hash_equals(session('checkout_key', ''), $r->string('checkout_key')->toString()), 419);
        $order = $orders->place($r->user(), $cart->cart(), $r->safe()->except('checkout_key'), $r->string('checkout_key')->toString());

        return to_route('order', $order);
    }

    public function history(Request $r): View
    {
        return view('platform.orders', ['orders' => Order::where('user_id', $r->user()->id)->with('roaster')->latest()->get()]);
    }

    public function order(Request $r, Order $order): View
    {
        app(OrderService::class)->expire();
        $order->refresh();
        abort_unless($order->user_id === $r->user()->id || $r->user()->is_admin, 403);

        return view('platform.order', ['order' => $order->load('items', 'roaster', 'shipment')]);
    }

    public function pay(Request $r, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->user_id === $r->user()->id, 403);
        $data = $r->validate(['scenario' => 'required|in:paid,failed,cancelled,pending', 'event' => 'required|uuid']);
        $orders->pay($order, $data['scenario'], 'demo:'.$order->id.':'.$data['event']);

        return back()->with('success', 'Het demo-betaalscenario is verwerkt.');
    }

    public function report(Request $r): RedirectResponse
    {
        $data = $r->validate(['kind' => 'required|in:recipe,review', 'target_id' => 'required|integer', 'reason' => 'required|string|min:5|max:1000']);
        ($data['kind'] === 'recipe' ? Recipe::class : Review::class)::findOrFail($data['target_id']);
        Report::create($data + ['user_id' => $r->user()->id]);

        return back()->with('success', 'Bedankt. Beheer bekijkt je melding.');
    }

    public function admin(Request $r): View
    {
        Gate::authorize('admin');
        app(OrderService::class)->expire();
        $q = Order::with('roaster');
        if ($r->filled('status')) {
            $q->where('status', $r->string('status')->toString());
        }
        if ($r->filled('q')) {
            $q->where('number', 'like', '%'.$r->string('q').'%');
        }

        return view('platform.admin', ['orders' => $q->latest()->get(), 'reports' => Report::where('status', 'open')->get(), 'coffees' => Coffee::with('variants', 'batches', 'roaster')->get(), 'roasters' => Roaster::all(), 'recipes' => Recipe::with('user')->latest()->get(), 'reviews' => Review::with('user')->latest()->get()]);
    }

    public function fulfill(Request $r, Order $order, OrderService $orders): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $r->validate(['action' => 'required|in:transfer,accept,ship,deliver', 'fail' => 'nullable|boolean']);
        $orders->fulfill($order, $data['action'], (bool) ($data['fail'] ?? false));

        return back()->with('success', 'Bestelstatus bijgewerkt.');
    }

    public function moderate(Request $r): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $r->validate(['kind' => 'required|in:recipe,review', 'target_id' => 'required|integer', 'reason' => 'required|string|min:5|max:1000']);
        $model = ($data['kind'] === 'recipe' ? Recipe::class : Review::class)::findOrFail($data['target_id']);
        $model->update(['hidden' => true, 'moderation_reason' => $data['reason']]);
        Report::where('kind', $data['kind'])->where('target_id', $data['target_id'])->update(['status' => 'resolved']);

        return back()->with('success', 'Content verborgen; reden vastgelegd.');
    }

    public function inventory(Request $r, CoffeeVariant $variant): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $r->validate(['stock' => 'required|integer|min:0|max:10000', 'price_cents' => 'required|integer|min:1|max:100000']);
        DB::transaction(function () use ($variant, $data) {
            $locked = CoffeeVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $locked->update($data);
        });

        return back()->with('success', 'Variant bijgewerkt.');
    }

    public function batch(Request $r): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $r->validate(['coffee_id' => 'required|exists:coffees,id', 'code' => 'required|string|max:100|unique:roast_batches,code', 'roasted_at' => 'required|date|before_or_equal:today', 'stock' => 'required|integer|min:0|max:10000']);
        RoastBatch::create($data);

        return back()->with('success', 'Brandbatch toegevoegd.');
    }

    public function roasterPolicy(Request $r, Roaster $roaster): RedirectResponse
    {
        Gate::authorize('admin');
        $data = $r->validate(['max_roast_days' => 'required|integer|min:1|max:'.config('platform.max_roast_days'), 'shipping_cents' => 'required|integer|min:0|max:2000', 'shipping_policy' => 'required|string|max:200']);
        $roaster->update($data);

        return back()->with('success', 'Roasterbeleid opgeslagen.');
    }
}
