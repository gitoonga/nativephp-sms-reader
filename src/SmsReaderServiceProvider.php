<?php

namespace Atendwa\SmsReader;

use Illuminate\Support\ServiceProvider;

class SmsReaderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('sms-reader', fn () => new SmsReader);
    }
}
