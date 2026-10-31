<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Device\HikvisionPushController;
use Barryvdh\Debugbar\Facades\Debugbar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Diagnostic log for device push requests. One line on arrival and one in
 * terminate(), which runs after the response has been sent, with the status
 * actually returned, the response header names, the processing stages the
 * controller recorded (request attributes "push.*") and the duration. Never
 * logs URLs (they carry the device token), header values or payloads.
 * Debugbar is switched off for these machine-to-machine requests.
 */
class LogDevicePushRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('push.started', microtime(true));
        if (class_exists(Debugbar::class) && app()->bound('debugbar')) {
            app('debugbar')->disable();
        }
        $this->write('received from '.$request->ip().' type='.strtok((string) $request->header('Content-Type'), ';').' length='.(int) $request->header('Content-Length'));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $attributes = $request->attributes;
        $milliseconds = (int) round((microtime(true) - (float) $attributes->get('push.started', microtime(true))) * 1000);

        $this->write(sprintf(
            'sent %d after %dms; device=%s parsed=%s outcome=%s serial=%s event_time=%s; response headers=[%s]',
            $response->getStatusCode(),
            $milliseconds,
            $attributes->get('push.device', '-'),
            $attributes->get('push.parsed', false) ? 'yes' : 'no',
            $attributes->get('push.outcome', 'none'),
            $attributes->get('push.serial', '-'),
            $attributes->get('push.event_time', '-'),
            implode(',', array_keys($response->headers->all())),
        ));
    }

    private function write(string $line): void
    {
        file_put_contents(HikvisionPushController::logPath(), now()->format('Y-m-d H:i:s.v').' '.$line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
