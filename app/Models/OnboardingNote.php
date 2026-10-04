<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Internal HR note on an onboarding. Never shown in self-service.
 */
class OnboardingNote extends Model
{
    protected $fillable = [
        'onboarding_id',
        'author_user_id',
        'author_name',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_id' => 'integer',
            'author_user_id' => 'integer',
        ];
    }
}
