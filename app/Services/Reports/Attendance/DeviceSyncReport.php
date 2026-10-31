<?php

namespace App\Services\Reports\Attendance;

use App\Services\Reports\Report;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Device synchronization and push history (integration monitoring). */
class DeviceSyncReport extends Report
{
    public static function key(): string
    {
        return 'attendance-device-sync';
    }

    public static function category(): string
    {
        return 'attendance';
    }

    public static function permission(): string
    {
        return 'attendance.device.view';
    }

    public function title(): string
    {
        return 'Device Synchronization';
    }

    public function description(): string
    {
        return 'Every device sync, push batch, import and connection test with its outcome and counts.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'date_from', 'label' => 'From', 'type' => 'date', 'required' => true, 'default' => now()->subDays(7)->toDateString()],
            ['key' => 'date_to', 'label' => 'To', 'type' => 'date', 'required' => true, 'default' => now()->toDateString()],
            ['key' => 'device_id', 'label' => 'Device', 'type' => 'select', 'options' => DB::table('attendance_devices')->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'title' => $d->name])->all()],
            ['key' => 'status', 'label' => 'Outcome', 'type' => 'select', 'options' => array_map(fn ($s) => ['value' => $s, 'title' => ucfirst($s)], ['succeeded', 'partial', 'failed'])],
        ];
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'device_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['succeeded', 'partial', 'failed'])],
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            'started' => 'Started', 'device' => 'Device', 'vendor' => 'Vendor', 'trigger' => 'Trigger', 'status' => 'Outcome',
            'fetched' => 'Fetched', 'inserted' => 'New', 'duplicates' => 'Duplicates', 'unmatched' => 'Unmapped', 'invalid' => 'Invalid',
            'attempts' => 'Attempts', 'error' => 'Error',
        ];
    }

    protected function tiebreaker(): string|array
    {
        return 'r.id';
    }

    public function query(array $filters): Builder
    {
        return DB::table('attendance_device_sync_runs as r')
            ->leftJoin('attendance_devices as d', 'd.id', '=', 'r.attendance_device_id')
            ->whereBetween('r.started_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59'])
            ->when($filters['device_id'] ?? null, fn ($q, $v) => $q->where('r.attendance_device_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('r.status', $v))
            ->select(['r.*', 'd.name as device_name', 'd.vendor'])
            ->orderByDesc('r.started_at')
            ->orderByDesc('r.id');
    }

    public function mapRow(mixed $row, array $filters): array
    {
        return [
            'started' => (string) $row->started_at, 'device' => $row->device_name, 'vendor' => $row->vendor, 'trigger' => $row->trigger,
            'status' => $row->status, 'fetched' => (int) $row->fetched, 'inserted' => (int) $row->inserted, 'duplicates' => (int) $row->duplicates,
            'unmatched' => (int) $row->unmatched, 'invalid' => (int) $row->invalid, 'attempts' => (int) $row->attempts,
            'error' => trim(($row->error_code ? $row->error_code.': ' : '').($row->error_message ?? '')),
        ];
    }
}
