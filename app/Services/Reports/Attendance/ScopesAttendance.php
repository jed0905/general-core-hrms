<?php

namespace App\Services\Reports\Attendance;

use App\Services\Attendance\AttendanceScope;
use Illuminate\Contracts\Database\Query\Builder;

/** Limits attendance report rows to the employees the viewer may see (AttendanceScope). */
trait ScopesAttendance
{
    protected function scopeEmployees(Builder $query, string $column = 'e.id'): Builder
    {
        return auth()->check() ? app(AttendanceScope::class)->apply($query, auth()->user(), $column) : $query->whereRaw('1 = 0');
    }

    protected static function hm(int|string|null $minutes): string
    {
        $minutes = (int) $minutes;

        return $minutes ? sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60) : '';
    }
}
