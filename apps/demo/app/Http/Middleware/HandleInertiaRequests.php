<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => fn () => [
                'user' => $request->user(),
            ],
            'app' => [
                'name' => config('app.name'),
            ],
        ]);
    }
}
