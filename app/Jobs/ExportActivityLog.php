<?php

namespace App\Jobs;

use App\Support\ActivityLogQuery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Streams a large, filtered activity-log export to a CSV on disk so the web
 * request never has to build it inline. The filtered set is rebuilt from the
 * serialized viewer state via {@see ActivityLogQuery}, so a queued export always
 * matches what the admin was looking at.
 */
class ExportActivityLog implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    /**
     * @param  array<string, mixed>  $state  Serialized filter state from the viewer.
     * @param  int|null  $requestedBy  The admin who requested the export.
     * @param  string|null  $locale  The locale active when the export was requested.
     */
    public function __construct(
        public array $state,
        public ?int $requestedBy = null,
        public ?string $locale = null,
    ) {}

    public function handle(): void
    {
        App::setLocale($this->locale ?? config('app.locale'));

        $path = 'exports/activity-log-'.now()->format('Y-m-d_His').'-'.($this->requestedBy ?? 'system').'.csv';
        $disk = Storage::disk('local');

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, __('activity_logs.export.headers'));

        ActivityLogQuery::build($this->state)
            ->with('causer')
            ->orderBy('id', 'desc')
            ->chunk(1000, function ($activities) use ($handle): void {
                foreach ($activities as $activity) {
                    fputcsv($handle, $this->row($activity));
                }
            });

        rewind($handle);
        $disk->put($path, stream_get_contents($handle));
        fclose($handle);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('jobs')->error('Job failed: ExportActivityLog', [
            'job' => self::class,
            'exception' => $exception,
        ]);
    }

    /** @return array<int, string> */
    protected function row(Activity $activity): array
    {
        $properties = $activity->properties;

        return [
            $activity->id,
            $activity->created_at?->toDateTimeString() ?? '',
            $activity->log_name ?? '',
            $properties['module'] ?? '',
            $activity->event ?? '',
            $properties['context'] ?? '',
            $activity->causer?->name ?? '',
            $activity->subject_type ? class_basename($activity->subject_type).'#'.$activity->subject_id : '',
            $activity->description,
            $properties->toJson(),
        ];
    }
}
