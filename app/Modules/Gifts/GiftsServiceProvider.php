<?php

namespace App\Modules\Gifts;

use App\Modules\Cart\LineTypes;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Gifts\Cart\VoucherLine;
use App\Modules\Gifts\Cart\VoucherLines;
use App\Modules\Gifts\Listeners\SendVouchers;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class GiftsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(LineTypes::class, fn (LineTypes $types) => $types->register(VoucherLine::TYPE, VoucherLines::class));
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(OrderPaid::class, SendVouchers::class);
    }
}
