<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    protected $fillable = [
        'hotel_name',
        'name',
        'phone',
        'city',
        'email',
        'message',
        'source',
    ];
}
