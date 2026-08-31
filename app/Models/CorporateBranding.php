<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateBranding extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_logo',
        'primary_color',
        'secondary_color'
    ];
}
