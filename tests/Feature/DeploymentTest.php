<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentTest extends TestCase
{
    /**
     * Forge runs `php artisan optimize` on every deploy, which compiles every Blade view.
     */
    public function test_every_view_compiles_like_during_a_deploy(): void
    {
        try {
            $this->artisan('view:cache')->assertSuccessful();
        } finally {
            $this->artisan('view:clear');
        }
    }
}
