<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CoffeeVariant;
use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgresConcurrencyTest extends TestCase
{
    public function test_two_simultaneous_buyers_cannot_purchase_the_last_bag(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Run against the separate PostgreSQL test database.');
        }
        $this->assertStringEndsWith('_test', config('database.connections.pgsql.database'), 'Concurrency tests must use a dedicated _test database.');
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $variant = CoffeeVariant::first();
        $variant->update(['stock' => 1]);
        $processes = [];
        foreach ([1, 2] as $userId) {
            $cart = Cart::create(['session_key' => Str::uuid()->toString(), 'roaster_id' => 1, 'items' => [$variant->id => 1]]);
            $processes[] = new Process([PHP_BINARY, base_path('tests/Fixtures/place-order.php'), (string) $userId, (string) $cart->id, Str::uuid()->toString()], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'), 'DB_DATABASE' => config('database.connections.pgsql.database'), 'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password')]);
        }
        foreach ($processes as $p) {
            $p->start();
        }
        $results = [];
        foreach ($processes as $p) {
            $p->wait();
            $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput().$p->getOutput());
            $results[] = trim($p->getOutput());
        }
        sort($results);
        $this->assertSame(['accepted', 'declined'], $results);
        $this->assertSame(0, $variant->fresh()->stock);
        $this->assertSame(1, Order::count());
    }
}
