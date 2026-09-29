{{--
    Report PDF, rendered by spatie/laravel-pdf (App\Support\Dashboard\Reports\Writers\PdfWriter)
    with whichever driver config('laravel-pdf.driver') names. Written for the lowest common
    denominator, DOMPDF: CSS 2.1 + a little CSS3, no flexbox/grid, so layout uses tables.
    DejaVu Sans ships with DOMPDF and covers Latin, Turkish, Cyrillic, Greek and more; the
    Chromium drivers fall back through the rest of the font stack.

    The dataset arrives pre-split into $pages — one <table> per page with a page break
    between them, which keeps DOMPDF's memory flat and gives every page one heading row.

    Expects: $document (ReportDocument), $pages (list of pages, each a list of key => text),
             $numeric (key => right-align?), $truncated (bool), $limit (int).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { margin: 30px 32px 46px; }
        * { font-family: 'DejaVu Sans', 'Segoe UI', Roboto, Arial, sans-serif; }
        body { color: #1f2937; font-size: 8.5px; margin: 0; }

        .header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 14px; }
        .header td { vertical-align: bottom; }
        .brand { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        h1 { font-size: 18px; margin: 3px 0 0; color: #111827; }
        .meta { text-align: right; color: #4b5563; font-size: 8.5px; line-height: 1.5; }
        .meta strong { color: #111827; }

        .summary { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 16px; }
        .summary td { background: #f3f4f6; border-radius: 6px; padding: 9px 11px; width: 25%; }
        .summary .label { color: #6b7280; font-size: 8px; text-transform: uppercase; letter-spacing: .5px; }
        .summary .value { font-size: 14px; font-weight: bold; color: #111827; margin-top: 3px; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data.break { page-break-after: always; }
        table.data thead { display: table-header-group; }
        table.data th { background: #111827; color: #fff; font-weight: bold; text-align: left; padding: 6px 7px; font-size: 8px; }
        table.data td { padding: 5px 7px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.data tr:nth-child(even) td { background: #f9fafb; }
        table.data tr { page-break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; }

        .notice { margin-top: 10px; padding: 7px 10px; background: #fef3c7; color: #92400e; border-radius: 4px; }
        .empty { padding: 24px; text-align: center; color: #6b7280; }

        footer { position: fixed; bottom: -30px; left: 0; right: 0; color: #9ca3af; font-size: 7.5px; border-top: 1px solid #e5e7eb; padding-top: 6px; }
        footer .page:after { content: counter(page) " / " counter(pages); }
    </style>
</head>
<body>
    <footer>
        <table width="100%"><tr>
            <td>{{ config('app.name') }} · {{ $document->title }}</td>
            <td style="text-align: right;">{{ __('dashboard.reports.page', ['number' => '']) }}<span class="page"></span></td>
        </tr></table>
    </footer>

    <table class="header" width="100%"><tr>
        <td>
            <div class="brand">{{ config('app.name') }}</div>
            <h1>{{ $document->title }}</h1>
        </td>
        <td class="meta">
            @foreach ($document->meta as $label => $value)
                {{ $label }}: <strong>{{ $value }}</strong><br>
            @endforeach
        </td>
    </tr></table>

    @if (count($document->summary))
        <table class="summary"><tr>
            @foreach ($document->summary as $label => $value)
                <td>
                    <div class="label">{{ $label }}</div>
                    <div class="value">{{ $value }}</div>
                </td>
            @endforeach
        </tr></table>
    @endif

    @foreach ($pages as $page)
        <table @class(['data', 'break' => ! $loop->last])>
            <thead>
                <tr>
                    @foreach ($document->columns as $key => $heading)
                        <th @class(['num' => $numeric[$key]])>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($page as $row)
                    <tr>
                        @foreach ($row as $key => $value)
                            <td @class(['num' => $numeric[$key]])>{{ $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td class="empty" colspan="{{ count($document->columns) }}">{{ __('dashboard.reports.empty_dataset') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    @if ($truncated)
        <div class="notice">{{ __('dashboard.reports.pdf_truncated', ['count' => number_format($limit)]) }}</div>
    @endif
</body>
</html>
