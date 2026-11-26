<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 8px; }
        h1 { margin: 0 0 4px; font-size: 17px; }
        h2 { margin: 14px 0 6px; font-size: 11px; }
        .muted { color: #6b7280; }
        .meta { margin: 10px 0; width: 100%; }
        .meta td { padding: 2px 6px 2px 0; vertical-align: top; }
        .summary { border-collapse: collapse; margin: 8px 0 12px; }
        .summary th, .summary td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
        table.data { border-collapse: collapse; width: 100%; table-layout: auto; }
        table.data th { background: #1d4ed8; color: #fff; font-weight: bold; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 4px; word-wrap: break-word; }
        .notice { margin: 8px 0; padding: 6px; border: 1px solid #f59e0b; background: #fffbeb; }
        .number { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @if($company?->company_name)<div>{{ $company->company_name }}</div>@endif
    @if($company?->address || $company?->phone || $company?->email || $company?->website)
        <div class="muted">
            {{ $company?->address }}
            @if($company?->phone) · {{ $company->phone }} @endif
            @if($company?->email) · {{ $company->email }} @endif
            @if($company?->website) · {{ $company->website }} @endif
        </div>
    @endif
    <div class="muted">Generated: {{ $generated_at }}</div>

    @if(count($filters))
        <h2>Selected Filters</h2>
        <table class="meta">
            @foreach($filters as $label => $value)
                <tr><td><strong>{{ $label }}</strong></td><td>{{ $value }}</td></tr>
            @endforeach
        </table>
    @endif

    @if(count($summary))
        <h2>Summary</h2>
        <table class="summary">
            @foreach($summary as $label => $value)
                @if(!is_array($value))
                    <tr>
                        <th>{{ str($label)->replace('_', ' ')->title() }}</th>
                        <td>{{ is_numeric($value) ? number_format((float) $value, 2) . (str_contains(strtolower((string) $label), 'value') ? ' ' . $currency : '') : $value }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endif

    @if($truncated)
        <div class="notice">PDF output is limited to the first 1,000 filtered rows. Export to Excel for the complete result set.</div>
    @endif

    <table class="data">
        <thead>
            <tr>@foreach($columns as $column)<th>{{ $column['label'] }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $column)
                        @php($value = data_get($row, $column['key']))
                        <td class="{{ in_array($column['type'], ['currency', 'number'], true) ? 'number' : '' }}">
                            @if($value === null || $value === '')
                                —
                            @elseif($column['type'] === 'currency' || $column['type'] === 'number')
                                {{ number_format((float) $value, 2) }}{{ $column['type'] === 'currency' ? ' ' . $currency : '' }}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">No records found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
