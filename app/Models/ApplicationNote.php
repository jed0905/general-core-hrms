<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Internal recruitment note. Never shown to applicants.
 */
class ApplicationNote extends Model
{
    protected $fillable = [
        'application_id',
        'author_user_id',
        'author_employee_id',
        'author_name',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'application_id' => 'integer',
            'author_user_id' => 'integer',
            'author_employee_id' => 'integer',
        ];
    }
}
