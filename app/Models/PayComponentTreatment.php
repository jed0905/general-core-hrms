<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one country's localization treats a pay component for tax and
 * contributions. The keys are defined by that localization.
 */
class PayComponentTreatment extends Model
{
    protected $fillable = ['pay_component_id', 'country_code', 'tax_treatment', 'contribution_treatment'];

    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }
}
