<?php

namespace App\Services\Payroll\Rules;

use RuntimeException;

/**
 * No usable statutory rule version for a key and date. Calculation of the
 * affected employee stops with an error exception; nothing is defaulted.
 */
class MissingStatutoryRuleException extends RuntimeException
{
    public static function for(string $country, string $ruleKey, string $date, ?string $variant = null, ?string $jurisdiction = null): self
    {
        $qualifiers = array_filter(['variant '.$variant => $variant, 'jurisdiction '.$jurisdiction => $jurisdiction]);

        return new self(sprintf(
            'No statutory rule version %s.%s effective on %s%s. Enter it with its official source in Payroll > Statutory Rules.',
            $country,
            $ruleKey,
            $date,
            $qualifiers ? ' ('.implode(', ', array_keys($qualifiers)).')' : ''
        ));
    }
}
