<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @internal Endpoints do sino de notificações. Escopados sempre ao
 * usuário autenticado via `$user->notifications()` (anti-IDOR: um id
 * de outro dono resolve 404 por findOrFail).
 */
final class NotificationController
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $filter = $request->string('filter')->toString();

        $paginator = $user->notifications()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->through(static fn ($n): array => [
                'id' => $n->id,
                'type' => class_basename($n->type),
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]);

        return Inertia::render('arqel::notifications', [
            'history' => $paginator,
            'filter' => $filter === 'unread' ? 'unread' : 'all',
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $this->user($request)->notifications()->findOrFail($notification)->markAsRead();

        return redirect()->back()->with('success', __('arqel::notifications.marked_read'));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $this->user($request)->unreadNotifications->markAsRead();

        return redirect()->back()->with('success', __('arqel::notifications.all_marked_read'));
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $this->user($request)->notifications()->findOrFail($notification)->delete();

        return redirect()->back()->with('success', __('arqel::notifications.deleted'));
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user;
    }
}
