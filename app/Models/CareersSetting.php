<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Careers-site texts (one row). Company name, contact details and logo come
 * from the existing organization and branding settings.
 */
class CareersSetting extends Model
{
    protected $fillable = ['headline', 'introduction', 'application_instructions', 'privacy_notice', 'updated_by'];

    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrFail();
    }
}
