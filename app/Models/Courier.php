<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Courier extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'level',
        'status',
        'registered_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'registered_at' => 'datetime',
    ];
}
