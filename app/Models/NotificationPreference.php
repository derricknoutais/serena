<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'tenant_id',
        'hotel_id',
        'event_key',
        'roles',
        'channels',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'channels' => 'array',
        ];
    }
}
