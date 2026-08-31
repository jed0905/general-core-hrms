<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'shortcut',
        'parent_id',
    ];

    /**
     * Get the parent department that this department belongs to.
     */
    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Get all child departments under this department.
     */
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }
}
