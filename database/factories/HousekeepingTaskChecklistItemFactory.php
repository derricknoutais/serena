<?php

namespace Database\Factories;

use App\Models\HousekeepingChecklistItem;
use App\Models\HousekeepingTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HousekeepingTaskChecklistItem>
 */
class HousekeepingTaskChecklistItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checklistItem = HousekeepingChecklistItem::query()->first()
            ?? HousekeepingChecklistItem::factory()->create();
        $task = HousekeepingTask::query()->first()
            ?? HousekeepingTask::factory()->create();

        return [
            'task_id' => $task->id,
            'checklist_item_id' => $checklistItem->id,
            'is_ok' => true,
            'note' => null,
        ];
    }
}
