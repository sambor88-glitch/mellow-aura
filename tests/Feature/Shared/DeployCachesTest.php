<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The Forge deploy script runs `artisan optimize`; a failing step stops the deploy.
 */
class DeployCachesTest extends TestCase
{
    public function test_views_can_be_cached(): void
    {
        $this->assertSame(0, Artisan::call('view:cache'));

        Artisan::call('view:clear');
    }

    public function test_compiled_view_path_does_not_depend_on_the_directory_existing(): void
    {
        $this->assertSame(storage_path('framework/views'), config('view.compiled'));
    }
}
