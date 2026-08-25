<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TardinessNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'month',
        'year',
        'last_notified_count',
    ];

    public function employee(){
        $this->belongsTo(Employee::class);
    }
}
