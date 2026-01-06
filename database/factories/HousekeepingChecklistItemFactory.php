<?php

namespace Database\Factories;

use App\Models\HousekeepingChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HousekeepingChecklistItem>
 */
class HousekeepingChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checklist = HousekeepingChecklist::query()->first()
            ?? HousekeepingChecklist::factory()->create();

        return [
            'checklist_id' => $checklist->id,
            'label' => 'Item '.fake()->word(),
            'sort_order' => 0,
            'is_required' => false,
            'is_active' => true,
        ];
    }
}
