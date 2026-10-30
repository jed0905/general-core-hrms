<?php

namespace Tests\Unit\Payroll;

use App\Services\Payroll\Support\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Money math is decimal-string only: no floats, explicit rounding mode, and
 * precision chosen by the caller (currency minor units 0, 2 or 3).
 */
class DecimalTest extends TestCase
{
    #[Test]
    public function arithmetic_is_exact_where_floats_are_not(): void
    {
        $this->assertSame('0.3000000000', Decimal::add('0.1', '0.2'));
        $this->assertSame('3333.3333333333', Decimal::div('10000', '3'));
        $this->assertSame('-5.0000000000', Decimal::neg('5'));
        $this->assertSame('6.0000000000', Decimal::sum(['1', '2', '3']));
    }

    #[Test]
    public function floats_are_rejected_when_values_enter_the_engine(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::of(0.1);
    }

    #[Test]
    public function non_numeric_strings_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::add('1e3', '1');
    }

    #[Test]
    public function division_by_zero_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::div('1', '0');
    }

    #[Test]
    public function unknown_rounding_modes_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::round('1.005', 2, 'bankers');
    }

    public static function roundingCases(): array
    {
        return [
            'half_up 2dp up' => ['1.005', 2, 'half_up', '1.01'],
            'half_up 2dp down' => ['1.0049', 2, 'half_up', '1.00'],
            'half_up negative' => ['-1.005', 2, 'half_up', '-1.01'],
            'half_even to even (down)' => ['2.125', 2, 'half_even', '2.12'],
            'half_even to even (up)' => ['2.135', 2, 'half_even', '2.14'],
            'half_even above half' => ['2.1251', 2, 'half_even', '2.13'],
            'up any remainder' => ['2.1201', 2, 'up', '2.13'],
            'up exact' => ['2.12', 2, 'up', '2.12'],
            'down truncates' => ['2.129', 2, 'down', '2.12'],
            'zero minor units (JPY-like)' => ['1234.5', 0, 'half_up', '1235'],
            'three minor units (KWD-like)' => ['1.2345', 3, 'half_up', '1.235'],
            'three minor units half_even' => ['1.2345', 3, 'half_even', '1.234'],
            'negative rounds to zero without sign' => ['-0.001', 2, 'half_up', '0.00'],
        ];
    }

    #[Test]
    #[DataProvider('roundingCases')]
    public function rounding_follows_the_requested_mode_and_precision(string $value, int $places, string $mode, string $expected): void
    {
        $this->assertSame($expected, Decimal::round($value, $places, $mode));
    }
}
