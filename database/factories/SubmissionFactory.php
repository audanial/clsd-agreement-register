<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->requester(),
            'campus_id' => fn () => Campus::firstOrCreate(
                ['code' => 'TEST'],
                ['name' => 'Fictional Test Campus', 'is_institute' => false, 'sort_order' => 999, 'is_active' => true]
            )->id,
            'agreement_id' => null,
            'title' => fake()->sentence(),
            'partner_name' => fake()->company(),
            'agreement_type' => fake()->randomElement(Submission::AGREEMENT_TYPES),
            'purpose' => fake()->paragraph(),
            'status' => Submission::STATUS_PENDING,
            'submitted_at' => now(),
        ];
    }
}
