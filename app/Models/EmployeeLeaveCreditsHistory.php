<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveCreditsHistory extends Model
{
    use HasFactory;

    public const ORIGIN_MONTHLY_EARNING = 'monthly_earning';
    public const ORIGIN_SERVICE_CREDIT = 'service_credit';
    public const ORIGIN_LEAVE_APPLICATION = 'leave_application';
    public const ORIGIN_UNDERTIME = 'undertime';
    public const ORIGIN_CORRECTION = 'correction';
    public const ORIGIN_MONETIZATION = 'monetization';
    public const ORIGIN_MANUAL_CREDIT = 'manual_credit';

    protected $append = ['is_expired'];
    protected $fillable = [
        'employee_id',
        'leave_id',
        'total_earned',
        'credit_addition',
        'credit_deduction',
        'balance',
        'credit_origin',
        'remarks',
        'special_leave_id',
        'document_type_number',
        'expiration_date_from',
        'expiration_date_to',
    ];


    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function leaveType()
    {
        return $this->belongsTo(Leave::class, 'leave_id', 'id');
    }

    public function specialLeave()
    {
        return $this->belongsTo(SpecialLeave::class, 'special_leave_id', 'id');
    }

    public function getIsExpiredAttribute()
    {
        return $this->expiration_date_to && now()->greaterThan($this->expiration_date_to);
    }

    protected function getLeaveShortcut(): ?string
    {
        // Special leave
        if ($this->special_leave_id && $this->specialLeave) {
            return $this->specialLeave->shortcut;
        }

        // Regular leave
        return $this->leaveType?->shortcut;
    }


    protected function actionTaken(): Attribute
    {
        return Attribute::make(
            get: function () {

                if (
                    (float) $this->credit_addition == 0 &&
                    (float) $this->credit_deduction == 0
                ) {
                    return null;
                }

                return match ($this->credit_origin) {

                    self::ORIGIN_LEAVE_APPLICATION => sprintf(
                        '%s - %s',
                        $this->created_at->format('m/d/Y'),
                        $this->getLeaveShortcut() ?? 'N/A'
                    ),

                    self::ORIGIN_MONETIZATION => sprintf(
                        'Monetization %s day%s',
                        $this->credit_deduction,
                        $this->credit_deduction == 1 ? '' : 's'
                    ),

                    default => null,
                };
            }
        );
    }

    // protected function particulars(): Attribute
    // {
    //     return Attribute::make(
    //         get: function () {

    //             if (
    //                 (float) $this->credit_addition == 0 &&
    //                 (float) $this->credit_deduction == 0
    //             ) {
    //                 return null;
    //             }

    //             // Skip CTO / COC completely
    //             if (in_array($this->getLeaveShortcut(), ['CTO', 'COC'])) {
    //                 return null;
    //             }

    //             $origin = $this->credit_origin;

    //             if ($origin === self::ORIGIN_MONTHLY_EARNING) {

    //                 // Monthly earned VL/SL belong in Earned column
    //                 if (in_array((int) $this->leave_id, [1, 3])) {
    //                     return null;
    //                 }

    //                 return $this->formatParticulars(
    //                     $this->credit_addition,
    //                     $this->getLeaveShortcut()
    //                 );
    //             }

    //             if (
    //                 in_array($origin, [
    //                     self::ORIGIN_LEAVE_APPLICATION,
    //                     self::ORIGIN_UNDERTIME,
    //                     self::ORIGIN_CORRECTION,
    //                     self::ORIGIN_MONETIZATION,
    //                     self::ORIGIN_MANUAL_CREDIT,
    //                 ])
    //             ) {

    //                 $credits = $this->credit_deduction > 0
    //                     ? $this->credit_deduction
    //                     : $this->credit_addition;

    //                 return $this->formatParticulars(
    //                     $credits,
    //                     $this->getLeaveShortcut()
    //                 );
    //             }

    //             return null;
    //         }
    //     );
    // }

    protected function particulars(): Attribute
    {
        return Attribute::make(
            get: function () {

                $addition = (float) $this->credit_addition;
                $deduction = (float) $this->credit_deduction;

                // Skip empty rows
                if ($addition == 0 && $deduction == 0) {
                    return null;
                }

                $shortcut = $this->getLeaveShortcut();
                $origin = $this->credit_origin;

                // Skip CTO / COC completely
                if (in_array($shortcut, ['CTO', 'COC'])) {
                    return null;
                }

                /*
                 * VL / SL monthly earnings go to the EARNED column,
                 * therefore they should not appear in PARTICULARS.
                 *
                 * This also works for old records where credit_origin
                 * is NULL.
                 */
                if (
                    $addition > 0 &&
                    in_array((int) $this->leave_id, [1, 3])
                ) {
                    return null;
                }

                /*
                 * Deduction of VL / SL is displayed in the
                 * ABS/UND columns, not in PARTICULARS.
                 */
                if (
                    $deduction > 0 &&
                    in_array((int) $this->leave_id, [1, 3])
                ) {
                    return null;
                }

                /*
                 * Undertime
                 *
                 * If there is no leave type/shortcut, identify it as UND.
                 */
                if (
                    $origin === self::ORIGIN_UNDERTIME ||
                    ($deduction > 0 && !$shortcut && $origin === null)
                ) {
                    return $this->formatParticulars(
                        $deduction,
                        'UND'
                    );
                }

                /*
                 * Monthly earning of other leave types,
                 * e.g. Wellness Leave (WL), should appear
                 * in PARTICULARS.
                 */
                if (
                    $origin === self::ORIGIN_MONTHLY_EARNING &&
                    $addition > 0
                ) {
                    return $this->formatParticulars(
                        $addition,
                        $shortcut
                    );
                }

                /*
                 * Leave application, correction, monetization,
                 * manual credit, or old records with no origin.
                 */
                $credits = $deduction > 0
                    ? $deduction
                    : $addition;

                return $this->formatParticulars(
                    $credits,
                    $shortcut
                );
            }
        );
    }


    protected function formatParticulars(
        float $credits,
        ?string $shortcut
    ): string {
        $days = floor($credits);

        $remaining = ($credits - $days) * 8;

        $hours = floor($remaining);

        $minutes = round(($remaining - $hours) * 60);

        if ($minutes === 60) {
            $hours++;
            $minutes = 0;
        }

        return sprintf(
            '(%02d-%02d-%02d) %s',
            $days,
            $hours,
            $minutes,
            $shortcut ?? 'N/A'
        );
    }

    public function getInclusiveDateAttribute(): ?string
    {
        if (
            (float) $this->credit_addition == 0 &&
            (float) $this->credit_deduction == 0
        ) {
            return null;
        }

        $date = $this->created_at?->format('m/d/Y');

        // Service Credit
        if (
            $this->credit_origin === self::ORIGIN_SERVICE_CREDIT &&
            (float) $this->credit_addition > 0
        ) {
            return sprintf(
                'SR %.2f %s',
                $this->credit_addition,
                $date
            );
        }

        // All other credit additions/deductions
        $shortcut = $this->getLeaveShortcut();

        if ($shortcut) {
            return sprintf(
                '%s %s',
                $shortcut,
                $date
            );
        }

        return $date;
    }



}
