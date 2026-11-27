<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);
        });
        Schema::create('roasters', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('city');
            $t->text('story');
            $t->string('color');
            $t->unsignedInteger('max_roast_days')->default(14);
            $t->unsignedInteger('shipping_cents')->default(495);
            $t->string('shipping_policy');
            $t->timestamps();
        });
        Schema::create('coffees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('roaster_id')->constrained();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('country');
            $t->string('region');
            $t->string('producer');
            $t->string('variety');
            $t->string('process');
            $t->string('roast');
            $t->string('method');
            $t->json('notes');
            $t->text('description');
            $t->string('color');
            $t->boolean('roast_to_order')->default(false);
            $t->unsignedInteger('max_roast_days')->nullable();
            $t->timestamps();
        });
        Schema::create('coffee_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coffee_id')->constrained();
            $t->unsignedInteger('grams');
            $t->unsignedInteger('price_cents');
            $t->unsignedInteger('stock')->default(0);
            $t->unique(['coffee_id', 'grams']);
            $t->timestamps();
        });
        Schema::create('roast_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coffee_id')->constrained();
            $t->string('code')->unique();
            $t->date('roasted_at');
            $t->unsignedInteger('stock');
            $t->timestamps();
        });
        Schema::create('recipes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coffee_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('title');
            $t->string('method');
            $t->decimal('dose', 6, 1);
            $t->decimal('water', 7, 1);
            $t->unsignedInteger('temperature');
            $t->unsignedInteger('seconds');
            $t->string('grinder');
            $t->string('grind');
            $t->string('machine')->nullable();
            $t->unsignedInteger('bloom')->nullable();
            $t->date('roasted_at')->nullable();
            $t->text('steps');
            $t->boolean('hidden')->default(false);
            $t->text('moderation_reason')->nullable();
            $t->timestamps();
        });
        Schema::create('recipe_votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->unique(['recipe_id', 'user_id']);
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coffee_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->unsignedTinyInteger('rating');
            $t->string('method');
            $t->text('body');
            $t->boolean('hidden')->default(false);
            $t->text('moderation_reason')->nullable();
            $t->unique(['coffee_id', 'user_id']);
            $t->timestamps();
        });
        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('kind');
            $t->unsignedBigInteger('target_id');
            $t->unique(['user_id', 'kind', 'target_id']);
            $t->timestamps();
        });
        Schema::create('carts', function (Blueprint $t) {
            $t->id();
            $t->string('session_key')->unique();
            $t->foreignId('roaster_id')->nullable()->constrained();
            $t->json('items');
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('checkout_key')->unique();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('roaster_id')->constrained();
            $t->string('number')->unique();
            $t->string('currency')->default('EUR');
            $t->unsignedInteger('subtotal_cents');
            $t->unsignedInteger('shipping_cents');
            $t->unsignedInteger('total_cents');
            $t->json('address');
            $t->json('promise');
            $t->string('payment_status')->default('pending');
            $t->string('status')->default('awaiting_payment');
            $t->string('shipment_status')->default('not_started');
            $t->timestamp('reserved_until')->nullable();
            $t->timestamp('released_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->foreignId('coffee_variant_id')->constrained();
            $t->foreignId('roast_batch_id')->nullable()->constrained();
            $t->string('name');
            $t->unsignedInteger('grams');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('price_cents');
            $t->json('batch_snapshot')->nullable();
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained();
            $t->string('provider')->default('demo');
            $t->string('status');
            $t->unsignedInteger('amount_cents');
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained();
            $t->string('tracking')->nullable();
            $t->string('status')->default('accepted');
            $t->timestamps();
        });
        Schema::create('integration_events', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('type');
            $t->json('payload');
            $t->timestamps();
        });
        Schema::create('external_mappings', function (Blueprint $t) {
            $t->id();
            $t->string('provider');
            $t->string('kind');
            $t->unsignedBigInteger('local_id');
            $t->string('external_id');
            $t->unique(['provider', 'kind', 'local_id']);
            $t->timestamps();
        });
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('kind');
            $t->unsignedBigInteger('target_id');
            $t->text('reason');
            $t->string('status')->default('open');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reports', 'external_mappings', 'integration_events', 'shipments', 'payments', 'order_items', 'orders', 'carts', 'favorites', 'reviews', 'recipe_votes', 'recipes', 'roast_batches', 'coffee_variants', 'coffees', 'roasters'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
