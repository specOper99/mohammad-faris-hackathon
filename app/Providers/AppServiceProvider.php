<?php

namespace App\Providers;

use App\Listeners\UpdateEmailOutbox;
use App\Models\Evaluation;
use App\Models\Submission;
use App\Models\Team;
use App\Policies\EvaluationPolicy;
use App\Policies\SubmissionPolicy;
use App\Policies\TeamPolicy;
use App\Services\Scan\MalwareScanner;
use App\Services\Scan\NoOpMalwareScanner;
use App\Services\Storage\FakeObjectStorage;
use App\Services\Storage\ObjectStorage;
use App\Services\Storage\S3ObjectStorage;
use App\Support\Clock;
use App\Support\SystemClock;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate as GateFacade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(MalwareScanner::class, NoOpMalwareScanner::class);
        $this->app->singleton(ObjectStorage::class, function ($app) {
            if ($app->environment('testing') || config('filesystems.default') === 'local') {
                return $app->make(FakeObjectStorage::class);
            }

            return $app->make(S3ObjectStorage::class);
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading($this->app->environment('local'));

        GateFacade::policy(Team::class, TeamPolicy::class);
        GateFacade::policy(Submission::class, SubmissionPolicy::class);
        GateFacade::policy(Evaluation::class, EvaluationPolicy::class);

        GateFacade::define('viewApiDocs', function ($user = null) {
            return ! $this->app->isProduction();
        });

        Event::listen(NotificationSent::class, [UpdateEmailOutbox::class, 'handleSent']);
        Event::listen(NotificationFailed::class, [UpdateEmailOutbox::class, 'handleFailed']);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        if (! $this->app->environment('testing')) {
            if (empty(config('app.key'))) {
                logger()->warning('APP_KEY missing');
            }
            if (empty(config('filesystems.disks.s3.bucket'))) {
                logger()->warning('AWS_BUCKET missing');
            }
            if (empty(config('exoplanet.frontend_url'))) {
                logger()->warning('FRONTEND_URL missing');
            }
        }

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(300)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('auth-login', fn (Request $r) => Limit::perMinutes(15, 5)->by($r->ip()));
        RateLimiter::for('auth-register', fn (Request $r) => Limit::perMinutes(15, 3)->by($r->ip()));
        RateLimiter::for('auth-activate', fn (Request $r) => Limit::perMinutes(15, 10)->by($r->ip()));
        RateLimiter::for('auth-forgot', fn (Request $r) => Limit::perMinutes(15, 3)->by($r->ip()));
        RateLimiter::for('invite', fn (Request $r) => Limit::perHour(20)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('upload-initiate', fn (Request $r) => Limit::perHour(60)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('public-get', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('public-contact', fn (Request $r) => Limit::perMinutes(15, 3)->by($r->ip()));
        RateLimiter::for('export', fn (Request $r) => Limit::perHour(10)->by(optional($r->user())->id ?: $r->ip()));
    }
}
