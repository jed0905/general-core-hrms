<div class="column">
    <div class="header">
        <table class="no-border">
            <tr class="campus">
                <th>DMMMSU {{ $dtr['operatingUnit'] }}</th>
            </tr>
            <tr class="title">
                <th>DAILY TIME RECORD</th>
            </tr>
            <tr class="date-range">
                <td>From: <b>{{ $startDate }}</b> To: <b>{{ $endDate }}</b></td>
            </tr>
        </table>
    </div>

    <div class="person">
        <table class="no-border">
            <tr>
                <th>Name:</th>
                <td colspan="3">{{ strtoupper($dtr['employeeName']) }}</td>
            </tr>
            <tr>
                <th>Position:</th>
                <td colspan="3">{{ strtoupper($dtr['position']) }}</td>
            </tr>
            <tr>
                <th>Department:</th>
                <td colspan="3">{{ strtoupper($dtr['department']) }}</td>
            </tr>
            <tr>
                <th>Regular Time:</th>
                <td>DEFAULT</td>
                <th style="text-align: right;">Payroll No.:</th>
                <td>1</td>
            </tr>
        </table>
    </div>

    <div class="data">
        <table>
            <tr>
                <th colspan="2">WORKING</th>
                <th colspan="2">AM</th>
                <th colspan="2">PM</th>
                <th colspan="2">HOURS</th>
            </tr>
            <!-- DTR DATA HEADER-->
            <tr>
                <td>Date</td>
                <td style="width: 10px;">Days</td>
                <td>In 1</td>
                <td>Out 1</td>
                <td>In 2</td>
                <td>Out 2</td>
                <td>UT</td>
                <td>OT</td>
            </tr>
            @for ($i = 1; $i <= 31; $i++)
                @php
                    $date = \Carbon\Carbon::create($year, $monthNumber, $i);
                    $dateStr = $date->toDateString();

                    $entry = collect($dtr['dtrArray'])->first(function ($item) use ($dateStr, $year) {
                        try {
                            $itemDate = \Carbon\Carbon::createFromFormat('j-M', $item['date'])->year($year);
                            return $itemDate->isSameDay(\Carbon\Carbon::parse($dateStr));
                        } catch (\Exception $e) {
                            return false;
                        }
                    });

                    $activities = $entry['activities'] ?? collect();

                    $activities = collect($activities);

                    $activityNames = $activities->pluck('title')->filter()->join(', ');
                    $activityTypes = $activities->pluck('type')->filter()->unique();

                    $hasWholeDayActivity = $activities->isNotEmpty();

                    $holiday = collect($entry['holidays'] ?? [])->first(fn($h) => $h->date === $dateStr);

                    $leave = $entry['leave'] ?? null;
                    $events = $entry['events'] ?? [];

                    $eventNames = collect($events)->pluck('name')->filter()->join(', ');

                    $hasWholeDayEvent = collect($events)->contains('coverage', 'whole_day');
                    $hasAmEvent = collect($events)->contains('coverage', 'am');
                    $hasPmEvent = collect($events)->contains('coverage', 'pm');
                    $hasCustomEvent = collect($events)->contains('coverage', 'custom');

                    $customEvent = collect($events)->firstWhere('coverage', 'custom');

                    $hasRecord =
                        $entry &&
                        (!empty($entry['check_in']) ||
                            !empty($entry['break_out']) ||
                            !empty($entry['break_in']) ||
                            !empty($entry['check_out']));
                @endphp
                <tr>
                    <td style="height: 17px;">{{ $i <= $daysInMonth ? $i . '-' . $monthName : '' }}</td>
                    <td>{{ $i <= $daysInMonth ? $date->format('D') : '' }}</td>

                    @if ($i <= $daysInMonth)
                        @php
                            $leave = $entry['leave'] ?? null;
                        @endphp


                        {{-- 1ST LEAVE --}}
                        @if ($leave === 'full_day')
                            <td colspan="6" class="text-center font-bold text-blue-600">ON LEAVE</td>
                        @elseif ($leave === 'half_day_am')
                            <td colspan="2" class="text-center text-blue-600 font-bold">ON LEAVE</td>

                            <td>{{ $entry['break_in'] ?? '' }}</td>
                            <td>{{ $entry['check_out'] ?? '' }}</td>
                            <td>{{ $entry['ut'] ?? '' }}</td>
                            <td>{{ $entry['ot'] ?? '' }}</td>
                        @elseif ($leave === 'half_day_pm')
                            <td>{{ $entry['check_in'] ?? '' }}</td>
                            <td>{{ $entry['break_out'] ?? '' }}</td>

                            <td colspan="2" class="text-center text-blue-600 font-bold">ON LEAVE</td>

                            <td>{{ $entry['ut'] ?? '' }}</td>
                            <td>{{ $entry['ot'] ?? '' }}</td>
                            {{-- 2ND EVENTS E.G. TRAVEL ORDER / OFFICIAL BUSINESS --}}
                        @elseif ($hasWholeDayEvent)
                            <td colspan="6" class="text-center font-bold text-green-600">
                                {{ $eventNames }}
                            </td>
                        @elseif ($hasAmEvent)
                            <td colspan="2" class="text-center text-green-600 font-bold">
                                {{ $eventNames }}
                            </td>
                        @elseif ($hasPmEvent)
                            <td>{{ $entry['check_in'] ?? '' }}</td>
                            <td>{{ $entry['break_out'] ?? '' }}</td>
                            <td colspan="2" class="text-center text-green-600 font-bold">
                                {{ $eventNames }}
                            </td>
                            <td>{{ $entry['ut'] ?? '' }}</td>
                            <td>{{ $entry['ot'] ?? '' }}</td>
                        @elseif ($hasCustomEvent)
                            @php
                                $columns = [
                                    ['key' => 'check_in', 'time' => '08:00:00'],
                                    ['key' => 'break_out', 'time' => '10:00:00'],
                                    ['key' => 'break_in', 'time' => '13:00:00'],
                                    ['key' => 'check_out', 'time' => '17:00:00'],
                                ];

                                $customStart = $customEvent['start_time'];
                                $customEnd = $customEvent['end_time'];

                                $coveredIndexes = collect($columns)
                                    ->keys()
                                    ->filter(function ($index) use ($columns, $customStart, $customEnd) {
                                        return $customStart <= $columns[$index]['time'] &&
                                            $customEnd >= $columns[$index]['time'];
                                    })
                                    ->values();

                                $startIndex = $coveredIndexes->first();
                                $endIndex = $coveredIndexes->last();
                            @endphp

                            @for ($col = 0; $col < 4; $col++)
                                @if ($startIndex !== null && $col == $startIndex)
                                    <td colspan="{{ $endIndex - $startIndex + 1 }}"
                                        class="text-center text-green-600 font-bold">
                                        {{ $eventNames }}
                                    </td>
                                    @php
                                        $col = $endIndex;
                                    @endphp
                                @elseif ($startIndex === null || $col < $startIndex || $col > $endIndex)
                                    <td>{{ $entry[$columns[$col]['key']] ?? '' }}</td>
                                @endif
                            @endfor

                            <td>{{ $entry['ut'] ?? '' }}</td>
                            <td>{{ $entry['ot'] ?? '' }}</td>
                            {{-- 3RD TIME LOGS --}}
                        @elseif ($hasRecord)
                            <td>{{ $entry['check_in'] ?? '' }}</td>
                            <td>{{ $entry['break_out'] ?? '' }}</td>
                            <td>{{ $entry['break_in'] ?? '' }}</td>
                            <td>{{ $entry['check_out'] ?? '' }}</td>
                            <td>{{ $entry['ut'] ?? '' }}</td>
                            <td>{{ $entry['ot'] ?? '' }}</td>
                            {{-- 4TH HOLIDAYS --}}
                        @elseif ($holiday && !$hasRecord && $activities->isEmpty())
                            <td colspan="6" class="text-center font-bold bg-red-200 text-red-600">
                                {{ $holiday->name }}
                            </td>
                            {{-- 5TH SUSPENSIONS --}}
                        @elseif ($activities->isNotEmpty() && $activityTypes->contains('suspension'))
                            <td colspan="6" class="text-center font-bold text-purple-600">
                                {{ $activityNames }}
                            </td>
                        @elseif ($activities->isNotEmpty() && !$activityTypes->contains('suspension'))
                            <td colspan="6" class="text-center font-bold text-yellow-600">
                                {{ $activityNames }}
                            </td>
                            {{-- 6TH TIMELOGS FALLBACK --}}
                        @else
                            <td>{{ $entry['check_in'] ?? '' }}</td>
                            <td>{{ $entry['break_out'] ?? '' }}</td>
                            <td>{{ $entry['break_in'] ?? '' }}</td>
                            <td>{{ $entry['check_out'] ?? '' }}</td>
                            <td>{{ $entry['ut'] ?? '' }}</td>
                            <td>{{ $entry['ot'] ?? '' }}</td>
                        @endif
                    @else
                        <td colspan="6"></td>
                    @endif
                </tr>
            @endfor
        </table>
    </div>

    <div class="footer">
        <table>
            <tr>
                <td>
                    A =
                    <span
                        style="display:inline-block; min-width:70px; border-bottom:1px solid #000; text-align:center;">
                        {{ $dtr['totalLeaveDays'] ?? '' }}
                    </span>
                </td>

                <td>
                    ROT =
                    <span
                        style="display:inline-block; min-width:70px; border-bottom:1px solid #000; text-align:center;">
                        {{ ($dtr['totalOvertimeFraction'] ?? '') !== '' ? number_format($dtr['totalOvertimeFraction'], 3) : '' }}
                    </span>
                </td>
                <td>LOT = __________</td>
            </tr>
            <tr>
                <td>
                    U =
                    <span
                        style="display:inline-block; min-width:70px; border-bottom:1px solid #000; text-align:center;">
                        {{ ($dtr['totalUndertimeFraction'] ?? '') !== '' ? number_format($dtr['totalUndertimeFraction'], 3) : '' }}
                    </span>
                </td>
                <td>SOT = __________</td>
                <td>
                    LWOP =
                    <span
                        style="display:inline-block; min-width:70px; border-bottom:1px solid #000; text-align:center;">
                        {{ $dtr['totalAbsentDays'] ?? '' }}
                    </span>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="padding-left: 40px; padding-right: 40px;">I Certify on my honor
                    that
                    the above is a true and correct report of the
                    hours work performed, record of which was daily at the time of arrival and departure
                    from
                    office.</td>
            </tr>
            <tr>
                <td colspan="3" style="border-bottom: rgb(97, 96, 96) ridge; padding-top: 30px;"></td>
            </tr>
            <tr>
                <td colspan="3" style="padding-bottom: 10px; border-bottom: black double;">SIGNATURE</td>
            </tr>
            <tr>
                <td colspan="3" style="padding-top: 15px;">VERIFIED as to prescribed office hours</td>
            </tr>
            <tr>
                <td colspan="3" style="border-bottom: rgb(97, 96, 96) ridge; padding-top: 35px;"></td>
            </tr>
            <tr>
                <td colspan="3">IN CHARGE</td>
            </tr>
            <tr>
                <td>>>>>>EMPLOYEE'S COPY</td>
            </tr>
        </table>
    </div>
</div>
