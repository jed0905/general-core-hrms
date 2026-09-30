<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $organization }}</title>
    <style>
        * { font-family: "DejaVu Sans", Arial, sans-serif; }
        body { font-size: 10px; color: #222; margin: 0; }
        .header { border-bottom: 2px solid #444; padding-bottom: 6px; margin-bottom: 10px; }
        .org { font-size: 11px; color: #555; }
        h1 { font-size: 16px; margin: 2px 0; }
        .meta { font-size: 9px; color: #555; }
        .filters { margin: 6px 0 10px; font-size: 9px; }
        .filters span { display: inline-block; margin-right: 12px; }
        .note { font-size: 9px; color: #555; margin: 2px 0; }
        h2 { font-size: 12px; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #eee; text-align: left; font-weight: bold; }
        th, td { border: 1px solid #ccc; padding: 3px 4px; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .count { font-size: 9px; color: #555; }
        .empty { padding: 12px; text-align: center; color: #777; border: 1px solid #ccc; }
        @media print { .no-print { display: none; } body { margin: 12mm; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="org">{{ $organization }}</div>
        <h1>{{ $title }}</h1>
        <div class="meta">Generated {{ $generatedAt }} by {{ $generatedBy }}</div>
    </div>

    <div class="filters">
        <strong>Filters:</strong>
        @forelse ($filters as $label => $value)
            <span>{{ $label }}: {{ $value }}</span>
        @empty
            <span>None</span>
        @endforelse
    </div>

    @foreach ($notes as $note)
        <div class="note">{{ $note }}</div>
    @endforeach

    @foreach ($summary as $section)
        <h2>{{ $section['title'] }}</h2>
        @if (empty($section['rows']))
            <div class="empty">No data.</div>
        @else
            <table>
                <thead><tr>@foreach ($section['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @if ($hasRows)
        @if (! empty($summary))<h2>Details</h2>@endif
        @if (empty($rows))
            <div class="empty">No records match the selected filters.</div>
        @else
            <table>
                <thead><tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>@foreach (array_keys($columns) as $key)<td>{{ $row[$key] ?? '' }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
            <div class="count">{{ number_format(count($rows)) }} record(s)</div>
        @endif
    @endif

    @if ($print)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
