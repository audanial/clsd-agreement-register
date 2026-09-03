<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\Campus;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'type' => fake()->randomElement(['LOI', 'NDA', 'MOA', 'MOU', 'SEA', 'MOC', 'ADDENDUM']),
            'partner_id' => Partner::factory(),
            'campus_id' => fn () => Campus::firstOrCreate(
                ['code' => 'TBD'],
                ['name' => 'Not Assigned', 'is_institute' => false, 'sort_order' => 999, 'is_active' => true]
            )->id,
            'pic_name' => null,
            'sector' => fake()->randomElement(['academic', 'industri']),
            'agreement_date' => fake()->date(),
            'effective_date' => null,
            'expiry_date' => null,
            'received_from_po_at' => null,
            'board_approved_at' => null,
            'signed_by_unikl_at' => null,
            'sent_to_partner_at' => null,
            'signed_date' => null,
            'document_status' => 'pending',
            'project_status' => 'not_started',
            'project_status_updated_at' => now(),
            'scope' => null,
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_status' => 'pending',
        ]);
    }

    public function awaitingPartner(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_status' => 'awaiting_partner',
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_status' => 'signed',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => today()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => today()->subDays(1),
        ]);
    }

    public function staleProjectStatus(): static
    {
        return $this->state(fn (array $attributes) => [
            'project_status_updated_at' => now()->subDays(Agreement::STALE_AFTER_DAYS + 1),
        ]);
    }
}
