<?php

namespace App\Services\Attendance\Devices\Streaming;

/**
 * Incremental parser for an endless multipart/mixed HTTP body (Hikvision
 * alertStream). Chunks arrive in arbitrary pieces; complete parts are
 * returned as they become available. Parts with Content-Length are cut by
 * length, others at the next boundary. The buffer is bounded so a malformed
 * stream cannot grow memory without limit.
 */
class MultipartStreamParser
{
    private const MAX_BUFFER = 4 * 1024 * 1024;

    private string $buffer = '';

    public function __construct(private readonly string $boundary) {}

    public static function boundaryFrom(?string $contentType): ?string
    {
        return $contentType && preg_match('/boundary="?([^";\s]+)"?/i', $contentType, $m) ? $m[1] : null;
    }

    /** @return array<int, array{headers: array<string, string>, body: string}> */
    public function feed(string $chunk): array
    {
        $this->buffer .= $chunk;
        $parts = [];
        $delimiter = '--'.$this->boundary;

        while (true) {
            $start = strpos($this->buffer, $delimiter);
            if ($start === false) {
                break;
            }

            $headerEnd = strpos($this->buffer, "\r\n\r\n", $start);
            $separator = 4;
            $lfEnd = strpos($this->buffer, "\n\n", $start);
            if ($lfEnd !== false && ($headerEnd === false || $lfEnd < $headerEnd)) {
                $headerEnd = $lfEnd;
                $separator = 2;
            }
            if ($headerEnd === false) {
                break;
            }

            $headers = $this->headers(substr($this->buffer, $start + strlen($delimiter), $headerEnd - $start - strlen($delimiter)));
            $bodyStart = $headerEnd + $separator;

            if (isset($headers['content-length']) && ctype_digit($headers['content-length'])) {
                $end = $bodyStart + (int) $headers['content-length'];
                if (strlen($this->buffer) < $end) {
                    break;
                }
            } else {
                $end = strpos($this->buffer, $delimiter, $bodyStart);
                if ($end === false) {
                    break;
                }
            }

            $parts[] = ['headers' => $headers, 'body' => trim(substr($this->buffer, $bodyStart, $end - $bodyStart))];
            $this->buffer = substr($this->buffer, $end);
        }

        if (strlen($this->buffer) > self::MAX_BUFFER) {
            $this->buffer = ''; // malformed or unbounded part: drop it and resynchronize on the next boundary
        }

        return $parts;
    }

    /** @return array<string, string> lower-cased header names */
    private function headers(string $block): array
    {
        $headers = [];
        foreach (preg_split('/\r?\n/', trim($block)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return $headers;
    }
}
