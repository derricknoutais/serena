<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HousekeepingChecklistItem extends Model
{
    /** @use HasFactory<\Database\Factories\HousekeepingChecklistItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'checklist_id',
        'label',
        'sort_order',
        'is_required',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(HousekeepingChecklist::class, 'checklist_id');
    }

    public function taskItems(): HasMany
    {
        return $this->hasMany(HousekeepingTaskChecklistItem::class, 'checklist_item_id');
    }
}
