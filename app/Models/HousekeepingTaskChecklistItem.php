<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HousekeepingTaskChecklistItem extends Model
{
    /** @use HasFactory<\Database\Factories\HousekeepingTaskChecklistItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'checklist_item_id',
        'is_ok',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_ok' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(HousekeepingTask::class, 'task_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(HousekeepingChecklistItem::class, 'checklist_item_id');
    }
}
