<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GovernmentPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'shortcut',
    ];

    public function position()
    {
        return $this->hasMany(Position::class);
    }
}
