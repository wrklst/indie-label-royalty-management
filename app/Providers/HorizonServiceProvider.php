<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Register the Horizon gate.
     *
     * Outside the local environment the dashboard is open only to the
     * signed-in users whose email addresses are listed in
     * HORIZON_ALLOWED_EMAILS. It shows jobs of every label, so being
     * the admin of a label is not enough.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            $allowed = config('royalties.horizon_emails');

            return $user !== null && is_array($allowed) && in_array(strtolower($user->email), $allowed, true);
        });
    }
}
