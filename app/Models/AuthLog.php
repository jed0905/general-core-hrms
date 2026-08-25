<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'auth_role',
        'auth_type',
        'auth_id',
        'attempt_status',
        'user_agent',
        'local_ip_address',
        'public_ip_address',
        'latitude',
        'longitude',
    ];
}
