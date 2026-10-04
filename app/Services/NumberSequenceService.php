<?php

namespace App\Services;

use App\Models\NumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Issues numbers from a configurable sequence (prefix + zero-padded counter + suffix).
 *
 * Concurrency: the sequence row is read with SELECT ... FOR UPDATE and the
 * counter is advanced in the same transaction. Call next() inside the
 * transaction that saves the numbered record: the row lock is then held until
 * that transaction commits, so concurrent callers are serialized and can never
 * receive the same number, and a rollback also returns the number to the pool.
 */
class NumberSequenceService
{
    /** Safety valve when skipping numbers that were already used manually. */
    private const MAX_SKIPS = 10000;

    /**
     * @param  (callable(string): bool)|null  $isTaken  returns true when a candidate is already used
     *                                                  (e.g. entered manually); it is then skipped
     */
    public function next(string $key, ?callable $isTaken = null): string
    {
        return DB::transaction(function () use ($key, $isTaken) {
            $sequence = NumberSequence::where('key', $key)->lockForUpdate()->first();

            if (! $sequence) {
                throw new RuntimeException("Number sequence [{$key}] is not configured.");
            }

            $period = $this->periodFor($sequence);
            if ($period !== null && $period !== $sequence->current_period) {
                // A new period (e.g. a new year) starts the counter again at 1.
                $sequence->forceFill(['current_period' => $period, 'next_number' => 1]);
            }

            $number = $sequence->next_number;
            $candidate = $this->format($sequence, $number);

            for ($skips = 0; $isTaken && $isTaken($candidate); $skips++) {
                if ($skips >= self::MAX_SKIPS) {
                    throw new RuntimeException("Number sequence [{$key}] could not find a free number.");
                }
                $candidate = $this->format($sequence, ++$number);
            }

            $sequence->forceFill(['next_number' => $number + 1])->save();

            return $candidate;
        });
    }

    /**
     * The number the next call would issue, without consuming it (for form hints only).
     */
    public function preview(string $key): ?string
    {
        $sequence = NumberSequence::where('key', $key)->first();

        if (! $sequence) {
            return null;
        }

        $period = $this->periodFor($sequence);
        $next = ($period !== null && $period !== $sequence->current_period) ? 1 : $sequence->next_number;

        return $this->format($sequence, $next);
    }

    /**
     * Whether blank input should be generated from the sequence.
     */
    public function autoGenerates(string $key): bool
    {
        return (bool) NumberSequence::where('key', $key)->value('auto_generate');
    }

    /**
     * The counter period "now" belongs to, or null when the sequence never resets.
     */
    protected function periodFor(NumberSequence $sequence, ?CarbonInterface $at = null): ?string
    {
        return $sequence->reset_period === NumberSequence::RESET_YEARLY ? ($at ?? now())->format('Y') : null;
    }

    public function format(NumberSequence $sequence, int $number, ?CarbonInterface $at = null): string
    {
        $at ??= now();
        $tokens = ['{YYYY}' => $at->format('Y'), '{YY}' => $at->format('y'), '{MM}' => $at->format('m')];

        return strtr($sequence->prefix, $tokens)
            .str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT)
            .strtr($sequence->suffix, $tokens);
    }
}
