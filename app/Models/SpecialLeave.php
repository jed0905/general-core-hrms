<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecialLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_type_id',
        'name',
        'shortcut',
    ];
    
    public function leaveType()
    {
        return $this->belongsTo(Leave::class);
    }
}
