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
        // during its boot() (earlier than this app provider's boot()), so it
        // would shadow any single-segment `/admin/*` route declared in
        // routes/web.php or this provider's boot() — resolving e.g.
        // `/admin/versions-demo` as resource="versions-demo" → 404. Registering
        // the static route here in register() (which runs before ALL boot()
        // methods) guarantees it is inserted first and wins the match.
        // See the Phase-5 findings ledger (CANDIDATE #7).
        Route::get('/admin/versions-demo', static fn () => Inertia::render('VersionsDemo'))
            ->middleware(['web', 'auth'])
            ->name('showcase.versions-demo');
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
