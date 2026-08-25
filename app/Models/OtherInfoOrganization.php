<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtherInfoOrganization extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'organization_name',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
