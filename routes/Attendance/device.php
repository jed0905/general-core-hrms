<?php

use App\Http\Controllers\Device\ConnectorEventController;
use App\Http\Controllers\Device\HikvisionPushController;
use App\Http\Controllers\Device\ZktecoPushController;
use App\Http\Middleware\LogDevicePushRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Device- and connector-initiated requests (stateless, no session/CSRF)
|--------------------------------------------------------------------------
| Each request is authenticated per device (secret URL token, registered
| serial) or per connector (HMAC signature + nonce). See
| docs/development/attendance/NETWORK_TOPOLOGIES.md and SECURITY_AND_PRIVACY.md.
*/

Route::post('attendance/push/hikvision/{token}', HikvisionPushController::class)
    ->middleware([LogDevicePushRequests::class, 'throttle:600,1'])
    ->where('token', '[A-Za-z0-9]{40,80}')
    ->name('attendance.device.hikvision.push');

Route::match(['get', 'post'], 'iclock/cdata', [ZktecoPushController::class, 'cdata'])
    ->middleware('throttle:600,1')
    ->name('attendance.device.zkteco.cdata');
Route::get('iclock/getrequest', [ZktecoPushController::class, 'getRequest'])
    ->middleware('throttle:600,1')
    ->name('attendance.device.zkteco.getrequest');
Route::post('iclock/devicecmd', [ZktecoPushController::class, 'deviceCommand'])
    ->middleware('throttle:600,1')
    ->name('attendance.device.zkteco.devicecmd');

Route::post('attendance/connector/events', ConnectorEventController::class)
    ->middleware('throttle:120,1')
    ->name('attendance.device.connector.events');
