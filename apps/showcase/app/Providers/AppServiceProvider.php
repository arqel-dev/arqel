<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Author;
use App\Models\Order;
use App\Models\Post;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Arqel's core provider registers a greedy `admin/{resource}` route
        // directly in ArqelServiceProvider::packageBooted() (its boot phase,
        // earlier than this app provider's boot()), so it would shadow any
        // single-segment `/admin/*` route declared in routes/web.php or this
        // provider's boot() — resolving e.g. `/admin/versions-demo` as
        // resource="versions-demo" → 404. The core's `{resource}` route IS
        // constrained (->where('resource', ...)), but its allowlist only
        // excludes auth slugs (login|logout|register|...), not app routes, so
        // `versions-demo` still matches. Registering the static route here in
        // register() (which runs before ALL boot() methods) guarantees it is
        // inserted first and wins the match.
        // See the Phase-5 findings ledger (CANDIDATE #7C).
        Route::get('/admin/versions-demo', static fn () => Inertia::render('VersionsDemo'))
            ->middleware(['web', 'auth'])
            ->name('showcase.versions-demo');

        // Same greedy-route rationale as versions-demo above: register the
        // tabs-free Grid form fixture here in register() so it is inserted
        // before the core `admin/{resource}` route and wins the match.
        Route::get('/admin/grid-form-demo', static fn () => Inertia::render('GridFormDemo'))
            ->middleware(['web', 'auth'])
            ->name('showcase.grid-form-demo');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'post' => Post::class,
            'order' => Order::class,
            'author' => Author::class,
            'ticket' => Ticket::class,
            // `user` must be mapped too: under enforceMorphMap (strict mode)
            // any model used polymorphically that is absent throws
            // ClassMorphViolationException. Spatie activitylog associates the
            // authenticated User as the activity `causer` (a MorphTo), so the
            // auth model participates polymorphically just like the subjects.
            'user' => User::class,
        ]);
    }
}
