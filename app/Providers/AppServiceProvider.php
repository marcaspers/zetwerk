<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\RoasterGateway;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, \App\Services\Demo\PaymentGateway::class);
        $this->app->bind(RoasterGateway::class, \App\Services\Demo\RoasterGateway::class);
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->is_admin);
        Blade::directive('euro', fn ($value) => "<?php echo '€ '.number_format(($value)/100,2,',','.'); ?>");
    }
}
