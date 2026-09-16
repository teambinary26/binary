<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 24px; }
        .header { text-align: center; border-bottom: 3px solid #c5a046; padding-bottom: 10px; margin-bottom: 14px; }
        .kicker { font-size: 9px; letter-spacing: 0.12em; text-transform: uppercase; color: #4b5563; margin: 0; }
        h1 { font-size: 16px; margin: 6px 0 2px; color: #0b3a6e; }
        .meta { margin: 0 0 12px; color: #4b5563; }
        .stats { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .stats td { border: 1px solid #d1d5db; padding: 8px 10px; width: 33.33%; }
        .stats .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.06em; color: #4b5563; }
        .stats .value { font-size: 13px; font-weight: bold; color: #0b3a6e; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #0b3a6e; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; }
        td.amount, th.amount, td.count, th.count { text-align: right; }
        tfoot td { font-weight: bold; background: #f3f4f6; }
        .empty { text-align: center; color: #6b7280; padding: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="kicker">{{ gov('republic') }} · {{ gov('lgu') }}</p>
        <h1>{{ gov('agency') }}</h1>
        <p class="meta">{{ $title }}</p>
    </div>
    <p class="meta">
        Generated {{ $generated_at }}
        @if($filter_summary)
            · {{ $filter_summary }}
        @endif
        · {{ $rows->count() }} program(s)
    </p>
    <table class="stats">
        <tr>
            <td>
                <div class="label">Total approved</div>
                <div class="value">{{ $approved }}</div>
            </td>
            <td>
                <div class="label">Total released</div>
                <div class="value">{{ $released }}</div>
            </td>
            <td>
                <div class="label">Total pending release</div>
                <div class="value">{{ $pending }}</div>
            </td>
        </tr>
    </table>
    <table>
        <thead>
            <tr>
                <th>Program</th>
                <th class="count">No. of releases</th>
                <th class="amount">Amount by program</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->name }}</td>
                    <td class="count">{{ $row->count }}</td>
                    <td class="amount">{{ peso($row->total, false) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="3">No releases match the current period.</td>
                </tr>
            @endforelse
        </tbody>
        @if($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="count">{{ $rows->sum('count') }}</td>
                    <td class="amount">{{ peso($rows->sum('total'), false) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
