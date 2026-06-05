<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
final class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => Str::ucfirst(fake()->sentence(4)),
            'status' => fake()->randomElement(['open', 'in_progress', 'resolved']),
        ];
    }
}
