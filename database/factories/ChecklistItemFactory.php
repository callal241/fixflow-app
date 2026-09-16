<?php

namespace Database\Factories;

use App\Enums\ChecklistPhase;
use App\Enums\ChecklistStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'label' => fake()->sentence(3),
            'note' => fake()->optional()->sentence(),
            'phase' => ChecklistPhase::PreRepair,
            'status' => ChecklistStatus::Pending,
            'checked_by_id' => null,
            'checked_at' => null,
        ];
    }

    // STATES //////////////////////////////////////////////////////////////////////////////////////

    /**
     * A quality-control item performed after the repair.
     */
    public function postRepair(): self
    {
        return $this->state(fn (array $attributes) => [
            'phase' => ChecklistPhase::PostRepair,
        ]);
    }

    /**
     * An item that has been checked (passed by default).
     */
    public function checked(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChecklistStatus::Passed,
            'checked_by_id' => User::factory(),
            'checked_at' => now(),
        ]);
    }

    // RELATIONS ///////////////////////////////////////////////////////////////////////////////////

    /**
     * Indicate that the item belongs to a specific ticket.
     */
    public function forTicket(Ticket $ticket): self
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $ticket->id,
            'business_id' => $ticket->business_id,
        ]);
    }
}
