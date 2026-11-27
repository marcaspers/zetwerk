<?php

namespace App\Services\Demo;

use App\Models\Order;

class RoasterGateway implements \App\Contracts\RoasterGateway
{
    public function transfer(Order $order, bool $fail = false): void
    {
        if ($fail) {
            throw new \RuntimeException('Gesimuleerde overdracht mislukt. Probeer opnieuw.');
        }
    }
}
