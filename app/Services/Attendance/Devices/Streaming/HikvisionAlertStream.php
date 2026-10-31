<?php

namespace App\Services\Attendance\Devices\Streaming;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Support\DeviceCommunicationException;

/**
 * One connection to a Hikvision device's ISAPI alertStream
 * (GET /ISAPI/Event/notification/alertStream, digest authentication, an
 * endless multipart/mixed body of JSON or XML event notifications).
 *
 * Uses ext-curl directly: Guzzle's streaming handler cannot do digest
 * authentication. Credentials stay in curl options and are never logged.
 * Returns when the connection ends, the device is silent for $idleSeconds,
 * or $shouldStop() returns true; the caller reconnects.
 */
class HikvisionAlertStream
{
    /**
     * @param  callable(array<string, mixed>): void  $onMessage  decoded notification
     * @param  callable(): bool  $shouldStop
     * @param  callable(string): void  $onConnected  receives the response content type
     * @return array{bytes: int, messages: int, malformed: int, stopped: bool}
     */
    public function listen(AttendanceDevice $device, callable $onMessage, callable $shouldStop, callable $onConnected, int $idleSeconds = 90): array
    {
        if (! $device->host) {
            throw new DeviceCommunicationException(DeviceCommunicationException::CONNECTION_FAILED, 'The device has no host configured.');
        }

        $stats = ['bytes' => 0, 'messages' => 0, 'malformed' => 0, 'stopped' => false];
        $status = 0;
        $contentType = null;
        $parser = null;

        $ch = curl_init($device->baseUrl().'/ISAPI/Event/notification/alertStream');
        curl_setopt_array($ch, [
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $device->username.':'.$device->password,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => max(10, $idleSeconds),
            CURLOPT_SSL_VERIFYPEER => (bool) $device->verify_tls,
            CURLOPT_SSL_VERIFYHOST => $device->verify_tls ? 2 : 0,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => fn () => $shouldStop() ? 1 : 0, // aborts promptly on a stop signal even when idle
            CURLOPT_HEADERFUNCTION => function ($handle, string $line) use (&$status, &$contentType) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                    $contentType = null;
                } elseif (stripos($line, 'Content-Type:') === 0) {
                    $contentType = trim(substr($line, 13));
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$status, &$contentType, &$parser, &$stats, $onMessage, $onConnected, $shouldStop) {
                if ($status !== 200) {
                    return strlen($chunk); // 401 challenge body or an error page; handled after curl returns
                }

                if ($parser === null) {
                    $parser = new MultipartStreamParser(MultipartStreamParser::boundaryFrom($contentType) ?? 'MIME_boundary');
                    $onConnected((string) $contentType);
                }

                $stats['bytes'] += strlen($chunk);
                foreach ($parser->feed($chunk) as $part) {
                    $message = $this->decode($part['headers']['content-type'] ?? '', $part['body']);
                    if ($message === null) {
                        $stats['malformed']++;

                        continue;
                    }
                    $stats['messages']++;
                    $onMessage($message);
                }

                return $shouldStop() ? 0 : strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($shouldStop()) {
            $stats['stopped'] = true;

            return $stats;
        }
        if ($status === 401) {
            throw new DeviceCommunicationException(DeviceCommunicationException::AUTH_FAILED, 'The device rejected the configured credentials (HTTP 401).');
        }
        if ($status !== 200) {
            throw new DeviceCommunicationException($errno === CURLE_OPERATION_TIMEDOUT ? DeviceCommunicationException::TIMEOUT : DeviceCommunicationException::CONNECTION_FAILED,
                $status ? "The device answered alertStream with HTTP {$status}." : 'Could not connect to the device (curl error '.$errno.').');
        }
        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new DeviceCommunicationException(DeviceCommunicationException::TIMEOUT, "No data from the device for {$idleSeconds} seconds.");
        }
        if ($errno !== 0) {
            throw new DeviceCommunicationException(DeviceCommunicationException::CONNECTION_FAILED, 'The event stream was interrupted (curl error '.$errno.').');
        }

        throw new DeviceCommunicationException(DeviceCommunicationException::CONNECTION_FAILED, 'The device closed the event stream.');
    }

    /** JSON or XML notification -> array, or null when unreadable. */
    private function decode(string $contentType, string $body): ?array
    {
        if ($body === '') {
            return null;
        }

        if (str_contains($contentType, 'json') || str_starts_with($body, '{')) {
            $data = json_decode($body, true);

            return is_array($data) ? $data : null;
        }

        if (str_starts_with($body, '<')) {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return $xml === false ? null : (json_decode((string) json_encode($xml), true) ?: null);
        }

        return null; // pictures and other binary parts are not used
    }
}
