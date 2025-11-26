<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use HasDomains;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public function getNameAttribute(): ?string
    {
        return $this->attributes['name'] ?? $this->data['name'] ?? null;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value;
        $data = $this->data ?? [];
        $data['name'] = $value;
        $this->data = $data;
    }

    /**
     * @return string[]
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'contact_email',
            'plan',
            'created_at',
            'updated_at',
        ];
    }
}
