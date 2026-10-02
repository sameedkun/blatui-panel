<?php

use App\Support\Dashboard\Analytics;
use App\Support\Dashboard\Overview;
use App\Support\Dashboard\Reports\Definitions;

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
|
| The single place an application shapes its dashboard. The core ships
| product-agnostic widgets, analytics sections and reports; a product adds
| its own by appending classes here (e.g. a VPN app adding a "Servers"
| analytics section or a "Bandwidth" report) — no Livewire or Blade changes.
|
| Every class declares its own permission and is hidden from anyone who
| lacks it. See App\Support\Dashboard\DashboardRegistry.
|
*/

return [

    /*
    | Reporting currency for money figures. Revenue, MRR, refunds and the
    | breakdowns sum only the transactions charged in this currency — amounts
    | in different currencies are never added together (no FX conversion yet).
    | Every other currency is shown exactly in Analytics → Revenue → "Sales by
    | currency".
    */
    'currency' => env('DASHBOARD_CURRENCY', 'USD'),

    /*
    | How long computed widgets/sections are cached, in seconds (0 disables).
    | The Refresh button on each page drops the cache immediately.
    */
    'cache_seconds' => (int) env('DASHBOARD_CACHE_SECONDS', 300),

    /*
    |--------------------------------------------------------------------------
    | Overview
    |--------------------------------------------------------------------------
    |
    | Widgets per slot (App\Support\Dashboard\Contracts\Widget). Slots render
    | top to bottom in decreasing visual weight:
    |   kpis → trends → status → activity / actions → health
    | Keep `kpis` to 4–5 and `trends` to 2 — the Overview answers "how is the
    | platform doing?" in ten seconds; everything else belongs in Analytics.
    |
    */
    'overview' => [
        'kpis' => [
            Overview\Kpis\TotalUsers::class,
            Overview\Kpis\PayingSubscribers::class,
            Overview\Kpis\Revenue::class,
            Overview\Kpis\MonthlyRecurringRevenue::class,
            Overview\Kpis\OpenTickets::class,
        ],
        'trends' => [
            Overview\Trends\UserGrowth::class,
            Overview\Trends\RevenueTrend::class,
        ],
        'status' => [
            Overview\Status\SubscriptionStatus::class,
            Overview\Status\SupportStatus::class,
        ],
        'activity' => [
            Overview\RecentActivity::class,
        ],
        'actions' => [
            Overview\QuickActions::class,
        ],
        'health' => [
            Overview\Health\ApiErrorRate::class,
            Overview\Health\ApiLatency::class,
            Overview\Health\FailedLogins::class,
            Overview\Health\BlockedTraffic::class,
            Overview\Health\FailedJobs::class,
        ],
    ],

    /*
    | Quick actions on the Overview (rendered by Overview\QuickActions).
    | `label`/`description` are translation keys; entries whose route is not
    | registered are skipped, and each is hidden without its permission.
    */
    'overview_actions' => [
        ['label' => 'dashboard.actions.create_user', 'icon' => 'user-plus', 'route' => 'admin.users.create', 'permission' => 'users.create'],
        ['label' => 'dashboard.actions.create_ticket', 'icon' => 'ticket-plus', 'route' => 'admin.tickets.create', 'permission' => 'tickets.create'],
        ['label' => 'dashboard.actions.create_plan', 'icon' => 'package-plus', 'route' => 'admin.plans.create', 'permission' => 'plans.create'],
        ['label' => 'dashboard.actions.create_announcement', 'icon' => 'megaphone', 'route' => 'admin.announcements.create', 'permission' => 'announcements.create'],
        ['label' => 'dashboard.actions.generate_report', 'icon' => 'file-chart-column', 'route' => 'admin.dashboard.reports', 'parameters' => ['generate' => 1], 'permission' => 'dashboard.reports.create'],
        ['label' => 'dashboard.actions.block_ip', 'icon' => 'shield-ban', 'route' => 'admin.blocked-ips.index', 'permission' => 'blocked-ips.create'],
    ],

    /*
    | Health thresholds for the Overview's snapshot.
    */
    'health' => [
        'latency_warning_ms' => 800,
        'latency_critical_ms' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Tabs on the Analytics page, in order (App\Support\Dashboard\Analytics\
    | AnalyticsSection). Each section builds rows of blocks for a date range.
    |
    */
    'analytics' => [
        Analytics\AudienceSection::class,
        Analytics\RevenueSection::class,
        Analytics\SubscriptionsSection::class,
        Analytics\SupportSection::class,
        Analytics\SecuritySection::class,
        Analytics\ApiSection::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
    'reports' => [

        // Report definitions offered on the Reports page (App\Support\Dashboard\Reports\ReportDefinition).
        'definitions' => [
            Definitions\UsersReport::class,
            Definitions\RevenueReport::class,
            Definitions\SubscriptionSummaryReport::class,
            Definitions\TicketsReport::class,
            Definitions\SupportPerformanceReport::class,
            Definitions\SecurityEventsReport::class,
        ],

        // Folder on the default filesystem disk that report files are written to.
        'directory' => 'reports',

        // Days a generated report (file + row) is kept before the daily prune removes it.
        'retention_days' => (int) env('DASHBOARD_REPORT_RETENTION_DAYS', 30),

        // The longest period a single report may cover, in days.
        'max_range_days' => 366,

        // Scheduled reports larger than this are emailed as a link instead of an attachment.
        'max_attachment_kb' => 10240,

        // PDF engines lay the whole document out in memory, so PDFs stop at this
        // many rows (with a note saying so). CSV/XLSX stream and have no limit.
        // The engine itself is config('laravel-pdf.driver') / LARAVEL_PDF_DRIVER.
        // Measured with DOMPDF: ~0.15 MB and ~20 ms per 8-column row, so 1000 rows
        // ≈ 150 MB / 20 s. Raise it together with pdf_memory_limit, or use a
        // Chromium driver, for bigger PDFs.
        'pdf_max_rows' => (int) env('DASHBOARD_REPORT_PDF_MAX_ROWS', 1000),

        // Memory ceiling while an in-process driver (DOMPDF) renders a report.
        'pdf_memory_limit' => env('DASHBOARD_REPORT_PDF_MEMORY_LIMIT', '512M'),

        // Recipients a single schedule may have.
        'max_recipients' => 10,
    ],

];
