<?php

namespace App\Modules\Admin;

use App\Models\User;
use App\Modules\Admin\Console\SetPanelUser;
use App\Modules\Admin\Support\PanelAccess;
use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Admin\Support\Search\QuickActions;
use App\Modules\Admin\Support\Search\ScreenSearch;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

class AdminServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // The search at the top of the panel (MA-308). Every module adds its own provider with callAfterResolving.
        $this->app->scoped(PanelSearch::class);
        $this->callAfterResolving(PanelSearch::class, fn (PanelSearch $search) => $search->add(new ScreenSearch)->add(new QuickActions));
    }

    public function boot(): void
    {
        parent::boot();

        // Every panel route asks this; the helper's allowed screens are listed in PanelAccess.
        Gate::define('panel-route', fn (User $user) => PanelAccess::allows($user, (string) request()->route()?->getName()));
        // Money matters, settings and accounts, for the owner only; views use it to hide links a helper can't open.
        Gate::define('manage-shop', fn (User $user) => $user->isOwner());

        if ($this->app->runningInConsole()) {
            $this->commands([SetPanelUser::class]);
        }
    }
}
