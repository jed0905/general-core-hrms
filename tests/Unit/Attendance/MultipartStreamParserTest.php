<?php

namespace Tests\Unit\Attendance;

use App\Services\Attendance\Devices\Streaming\MultipartStreamParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The alertStream body is endless multipart/mixed; chunks split anywhere.
 * Framing below is as observed on a DS-K1T321MFX (V4.61.0).
 */
class MultipartStreamParserTest extends TestCase
{
    private function part(string $json): string
    {
        return "--MIME_boundary\r\nContent-Disposition: form-data; name=\"AccessControllerEvent\"\r\nContent-Type: application/json; charset=\"UTF-8\"\r\nContent-Length: ".strlen($json)."\r\n\r\n".$json."\r\n";
    }

    #[Test]
    public function parts_are_returned_whole_however_the_stream_is_chunked(): void
    {
        $stream = $this->part('{"serial":1}').$this->part('{"serial":2}').$this->part('{"serial":3}');
        $parser = new MultipartStreamParser(MultipartStreamParser::boundaryFrom('multipart/mixed; boundary=MIME_boundary'));

        $bodies = [];
        foreach (str_split($stream, 7) as $chunk) {
            foreach ($parser->feed($chunk) as $part) {
                $bodies[] = $part['body'];
            }
        }

        $this->assertSame(['{"serial":1}', '{"serial":2}', '{"serial":3}'], $bodies);
    }

    #[Test]
    public function parts_without_content_length_end_at_the_next_boundary(): void
    {
        $parser = new MultipartStreamParser('MIME_boundary');

        $this->assertSame([], $parser->feed("--MIME_boundary\nContent-Type: application/xml\n\n<EventNotificationAlert/>\n"));
        $parts = $parser->feed("--MIME_boundary\nContent-Type: application/json\n\n{}\n--MIME_boundary");

        $this->assertSame(['<EventNotificationAlert/>', '{}'], array_column($parts, 'body'));
        $this->assertSame('application/xml', $parts[0]['headers']['content-type']);
    }

    #[Test]
    public function garbage_before_a_boundary_is_skipped(): void
    {
        $parser = new MultipartStreamParser('MIME_boundary');

        $parts = $parser->feed("junk\r\n".$this->part('{"ok":true}'));

        $this->assertSame(['{"ok":true}'], array_column($parts, 'body'));
    }
}
