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
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #0b3a6e; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; }
        td.amount, th.amount { text-align: right; }
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
        · {{ $releases->count() }} record(s)
    </p>
    <table>
        <thead>
            <tr>
                <th>Reference no.</th>
                <th>Application</th>
                <th>Applicant</th>
                <th>Program</th>
                <th class="amount">Amount</th>
                <th>Released</th>
                <th>Officer</th>
                <th>Verification code</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($releases as $row)
                <tr>
                    <td>{{ $row->reference_no }}</td>
                    <td>{{ $row->application?->application_no }}</td>
                    <td>{{ $row->application?->applicant?->full_name }}</td>
                    <td>{{ $row->application?->program?->name }}</td>
                    <td class="amount">{{ peso($row->amount, false) }}</td>
                    <td>{{ gov_datetime($row->released_at) }}</td>
                    <td>{{ $row->officer?->name }}</td>
                    <td>{{ $row->verification_code }}</td>
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="8">No released assistance matches the current filter.</td>
                </tr>
            @endforelse
        </tbody>
        @if($releases->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="4">Total</td>
                    <td class="amount">{{ peso($total, false) }}</td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
