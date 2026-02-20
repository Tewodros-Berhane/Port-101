<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 11px;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            background: #0f172a;
            color: #ffffff;
            text-align: center;
            line-height: 26px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
            margin-right: 8px;
        }
        .brand-name {
            display: inline-block;
            font-size: 14px;
            font-weight: bold;
            vertical-align: top;
            line-height: 26px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }
        .meta {
            color: #475569;
            font-size: 10px;
            margin-top: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            text-align: left;
            padding: 7px;
            font-size: 10px;
        }
        td {
            border: 1px solid #e2e8f0;
            padding: 7px;
            font-size: 10px;
            vertical-align: top;
        }
        .empty {
            text-align: center;
            color: #64748b;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <span class="brand">P101</span>
        <span class="brand-name">Port-101</span>
        <div class="title">{{ $report['title'] }}</div>
        <div class="meta">{{ $report['subtitle'] }}</div>
        <div class="meta">Generated at: {{ $generatedAt }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($report['columns'] as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if (count($report['rows']) === 0)
                <tr>
                    <td class="empty" colspan="{{ count($report['columns']) }}">
                        No records for the selected filters.
                    </td>
                </tr>
            @endif

            @foreach ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
