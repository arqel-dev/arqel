<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Dogfood example for the database-notifications feature (0.19): a plain
 * Laravel-native notification dispatched via `$user->notify(new
 * WelcomeNotification(...))`. `via()` returns only `['database']` — no mail
 * channel — so it persists exclusively to the `notifications` table that
 * backs the Arqel notification bell + `/admin/notifications` history page.
 *
 * `toArray()` demonstrates the `data` key convention documented in
 * `packages/core/SKILL.md` § Database Notifications: `title`/`body` render
 * the bell + list entry, `action_url` (optional) makes the entry a link,
 * `icon` (optional) picks the lucide icon in the bell dropdown (one of
 * `bell`/`check`/`info`/`alert`/`mail`/`user`).
 */
final class WelcomeNotification extends Notification
{
    public function __construct(private readonly string $message)
    {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, action_url: string, icon: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to Arqel',
            'body' => $this->message,
            'action_url' => '/admin',
            'icon' => 'bell',
        ];
    }
}
