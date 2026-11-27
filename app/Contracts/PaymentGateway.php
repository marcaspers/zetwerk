<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGateway
{
    public function status(Order $order, string $scenario): string;
}
