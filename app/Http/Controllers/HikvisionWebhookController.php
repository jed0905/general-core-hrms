<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HikvisionWebhookController extends Controller
{
    public function handleEvent(Request $request)
    {
        Log::info('========================================');
        Log::info('HIKVISION EVENT RECEIVED');
        Log::info('========================================');

        Log::info('Content Type', [
            'content_type' => $request->header('Content-Type'),
        ]);

        Log::info('Request Fields', [
            'fields' => array_keys($request->all()),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Hikvision multipart field
        |--------------------------------------------------------------------------
        |
        | Different firmware versions may use different multipart field names.
        |
        */

        $eventLog =
            $request->input('AccessControllerEvent')
            ?? $request->input('event_log');

        if (!$eventLog) {
            Log::warning('Hikvision request does not contain an event payload', [
                'input' => $request->all(),
            ]);

            return response('OK', 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Decode JSON
        |--------------------------------------------------------------------------
        */

        $event = json_decode($eventLog, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Invalid Hikvision event JSON', [
                'error' => json_last_error_msg(),
                'event' => $eventLog,
            ]);

            return response('OK', 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Log complete event
        |--------------------------------------------------------------------------
        */

        Log::info('Parsed Hikvision Event', [
            'event' => $event,
        ]);

        $eventType = $event['eventType'] ?? null;

        Log::info('Hikvision Event Type', [
            'eventType' => $eventType,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Process event
        |--------------------------------------------------------------------------
        */

        match ($eventType) {

            'AccessControllerEvent'
            => $this->handleAccessControl($event),

            'heartBeat'
            => Log::debug('Hikvision heartbeat received'),

            default
            => Log::info('Hikvision event ignored', [
                'eventType' => $eventType,
            ]),
        };

        return response('OK', 200);
    }


    protected function handleAccessControl(array $event): void
    {
        $accessEvent = $event['AccessControllerEvent'] ?? [];

        Log::info('Hikvision Access Control Event', [
            'device_id' => $event['deviceID'] ?? null,

            'device_serial' =>
            $event['shortSerialNumber'] ?? null,

            'device_name' =>
            $accessEvent['deviceName'] ?? null,

            'date_time' =>
            $event['dateTime'] ?? null,

            'major_event_type' =>
            $accessEvent['majorEventType'] ?? null,

            'sub_event_type' =>
            $accessEvent['subEventType'] ?? null,

            'serial_no' =>
            $accessEvent['serialNo'] ?? null,

            'front_serial_no' =>
            $accessEvent['frontSerialNo'] ?? null,

            'verify_mode' =>
            $accessEvent['currentVerifyMode'] ?? null,

            'attendance_status' =>
            $accessEvent['attendanceStatus'] ?? null,

            'employee_no' =>
            $accessEvent['employeeNo'] ?? null,

            'employee_no_string' =>
            $accessEvent['employeeNoString'] ?? null,

            'name' =>
            $accessEvent['name'] ?? null,

            'card_no' =>
            $accessEvent['cardNo'] ?? null,
        ]);
    }
}
