<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Author;
use App\Models\Order;
use App\Models\Post;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
