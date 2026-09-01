<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationGeneralInformation extends Model
{
    protected $fillable = [
        'name',
        'shortcut',
        'phone',
        'email',
        'country',
        'province',
        'city',
        'zip_code',
        'street1',
        'street2',
        'note'
    ];
}
