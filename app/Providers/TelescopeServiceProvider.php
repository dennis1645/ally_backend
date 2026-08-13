<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });

        Telescope::tag(function (IncomingEntry $entry) {
            $tags = [];

            $ip = request()->header('X-Forwarded-For') ?? request()->ip() ?? '127.0.0.1';
            $tags[] = 'IP:' . $ip;

            $user = request()->user() ?? (auth('web')->user() ?? null);
            if ($user && !empty($user->email)) {
                $tags[] = 'User:' . $user->email;
                $tags[] = 'Role:' . ($user->role ?? 'user');
            }

            if ($entry->type === 'request') {
                $status = $entry->content['response_status'] ?? null;
                if ($status) {
                    $tags[] = 'Status:' . $status;
                }
            }

            return array_unique($tags);
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (User $user) {
            return in_array(strtolower($user->email), [
                'juna.admin@gmail.com',
            ]) || ($user->role === 'admin');
        });
    }
}
