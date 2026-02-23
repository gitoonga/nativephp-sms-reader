<?php

namespace Atendwa\SmsReader\Tests;

use Atendwa\SmsReader\SmsReaderServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SmsReaderServiceProvider::class,
        ];
    }
}
