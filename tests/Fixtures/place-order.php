<?php

use App\Models\Cart;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    app(OrderService::class)->place(User::findOrFail((int) $argv[1]), Cart::findOrFail((int) $argv[2]), ['name' => 'Test', 'street' => 'Test 1', 'postcode' => '1234 AB', 'city' => 'Utrecht'], $argv[3]);
    echo 'accepted';
} catch (ValidationException $e) {
    echo 'declined';
}
