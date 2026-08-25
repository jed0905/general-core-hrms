<?php

namespace App\Services;

use App\Models\AttendanceLogEventTypes;
use Illuminate\Support\Facades\Log;

class AttendanceLogEventTypesService
{
    public function all()
    {
        Log::channel('input')->info('AttendanceLogEventTypesService@all called');

        try {
            $result = AttendanceLogEventTypes::orderBy('name')->get();
            Log::channel('output')->info('AttendanceLogEventTypesService@all result', [
                'count' => $result->count(),
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::channel('error')->error('AttendanceLogEventTypesService@all failed', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function create(array $data): AttendanceLogEventTypes
    {
        Log::channel('input')->info('AttendanceLogEventTypesService@create called', [
            'data' => $data,
        ]);

        try {
            $eventType = AttendanceLogEventTypes::create($data);

            Log::channel('output')->info('AttendanceLogEventTypesService@create result', [
                'id' => $eventType->id,
            ]);

            return $eventType;
        } catch (\Throwable $e) {
            Log::channel('error')->error('AttendanceLogEventTypesService@create failed', [
                'message' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    public function update(int $id, array $data): AttendanceLogEventTypes
    {
        Log::channel('input')->info('AttendanceLogEventTypesService@update called', [
            'id' => $id,
            'data' => $data,
        ]);

        try {
            $eventType = AttendanceLogEventTypes::findOrFail($id);
            $eventType->update($data);

            Log::channel('output')->info('AttendanceLogEventTypesService@update result', [
                'id' => $eventType->id,
            ]);

            return $eventType;
        } catch (\Throwable $e) {
            Log::channel('error')->error('AttendanceLogEventTypesService@update failed', [
                'id' => $id,
                'message' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    public function deactivate(int $id): AttendanceLogEventTypes
    {
        Log::channel('input')->info('AttendanceLogEventTypesService@deactivate called', [
            'id' => $id,
        ]);

        try {
            $eventType = AttendanceLogEventTypes::findOrFail($id);
            $eventType->update(['is_active' => false]);

            Log::channel('output')->info('AttendanceLogEventTypesService@deactivate result', [
                'id' => $eventType->id,
                'is_active' => $eventType->is_active,
            ]);

            return $eventType;
        } catch (\Throwable $e) {
            Log::channel('error')->error('AttendanceLogEventTypesService@deactivate failed', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        Log::channel('input')->info('AttendanceLogEventTypesService@delete called', [
            'id' => $id,
        ]);

        try {
            $eventType = AttendanceLogEventTypes::findOrFail($id);
            $eventType->delete();

            Log::channel('output')->info('AttendanceLogEventTypesService@delete success', [
                'id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('error')->error('AttendanceLogEventTypesService@delete failed', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}