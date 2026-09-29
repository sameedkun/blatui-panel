<x-mail::message>
# {{ __('dashboard.reports.mail.heading', ['title' => $report->title]) }}

{{ __('dashboard.reports.mail.intro', ['period' => $period]) }}

@if ($attached)
{{ __('dashboard.reports.mail.attached', ['format' => $report->format->label()]) }}
@else
{{ __('dashboard.reports.mail.too_large') }}
@endif

<x-mail::button :url="$url">
{{ __('dashboard.reports.mail.button') }}
</x-mail::button>

{{ __('dashboard.reports.mail.rows', ['count' => number_format((int) $report->row_count)]) }}

{{ config('app.name') }}
</x-mail::message>
