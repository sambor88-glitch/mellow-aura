<?php

namespace App\Modules\Shared;

class SharedServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        static::registerFactoryResolver();
    }
}
