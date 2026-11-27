<?php

use App\Services\OrderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('demo:expire', function (): void {
    $this->info(app(OrderService::class)->expire().' reserveringen vrijgegeven.');
});
Schedule::command('demo:expire')->everyMinute()->withoutOverlapping();
