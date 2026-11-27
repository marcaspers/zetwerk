<?php

namespace App\Services\Demo;

use App\Models\Order;

class PaymentGateway implements \App\Contracts\PaymentGateway
{
    public function status(Order $order, string $scenario): string
    {
        return match ($scenario) {
            'paid' => 'paid','failed' => 'failed','cancelled' => 'cancelled',default => 'pending'
        };
    }
}
