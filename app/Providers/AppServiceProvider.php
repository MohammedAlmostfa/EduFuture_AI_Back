<?php

namespace App\Providers;

use App\Events\Registered;
use App\Listeners\SendVerificationEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(Registered::class, SendVerificationEmail::class);
    }
}
