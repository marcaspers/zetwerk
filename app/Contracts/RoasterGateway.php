<?php

namespace App\Contracts;

use App\Models\Order;

interface RoasterGateway
{
    public function transfer(Order $order, bool $fail = false): void;
}
