<?php

namespace App\Support\Dashboard\Reports\Writers;

use App\Support\Dashboard\Reports\ReportDocument;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Print-ready PDF via spatie/laravel-pdf: landscape A4, a title block with the
 * report's context and headline figures, then the dataset as a table with a
 * repeated heading row. Rendered from the `reports.pdf` Blade view.
 *
 * The engine is config('laravel-pdf.driver') — DOMPDF by default (pure PHP),
 * or a Chromium-based driver (Gotenberg, Cloudflare, Browsershot) for large or
 * pixel-perfect output — switched with LARAVEL_PDF_DRIVER, no code change.
 *
 * Every driver lays the whole document out in memory, so a PDF holds at most
 * `dashboard.reports.pdf_max_rows` rows and says so when cut short; CSV/XLSX
 * are the formats for full, unbounded datasets.
 */
class PdfWriter extends ReportWriter
{
    /**
     * The dataset is split into one <table> per page (page break between them).
     * DOMPDF's table layout (its Cellmap) blows up memory on one huge table —
     * the "Allowed memory size exhausted in Cellmap.php" failure — while many
     * page-sized tables keep it flat, and each page still gets exactly one
     * heading row. The first page is shorter: the title block sits above it.
     */
    public const int ROWS_FIRST_PAGE = 14;

    public const int ROWS_PER_PAGE = 24;

    public function write(ReportDocument $document, string $path): int
    {
        $limit = max(1, (int) config('dashboard.reports.pdf_max_rows', 2000));
        [$rows, $rest] = $this->sample($document->rows, $limit);
        $truncated = false;

        foreach ($rest as $ignored) {
            $truncated = true;
            break;
        }

        $keys = array_keys($document->columns);
        $numeric = array_fill_keys($keys, false);

        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $numeric[$key] = $numeric[$key] || is_int($row[$key] ?? null) || is_float($row[$key] ?? null);
            }
        }

        $cells = array_map(
            fn (array $row): array => array_combine($keys, array_map(fn (string $key): string => $this->cellText($row[$key] ?? null), $keys)),
            $rows,
        );

        $this->withMemoryLimit(fn () => Pdf::view('reports.pdf', [
            'document' => $document,
            'pages' => $this->paginate($cells),
            'numeric' => $numeric,
            'truncated' => $truncated,
            'limit' => $limit,
        ])
            ->format(Format::A4)
            ->landscape()
            ->save($path));

        return count($rows);
    }

    /**
     * @param  list<array<string, string>>  $cells
     * @return list<list<array<string, string>>> rows per page (always at least one, possibly empty, page)
     */
    private function paginate(array $cells): array
    {
        $pages = [array_slice($cells, 0, self::ROWS_FIRST_PAGE)];

        foreach (array_chunk(array_slice($cells, self::ROWS_FIRST_PAGE), self::ROWS_PER_PAGE) as $page) {
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * In-process drivers (DOMPDF) render inside this PHP worker, so a report
     * gets a higher memory ceiling while it is laid out. Only ever raises the
     * limit, and only lowers it back when the process's memory allows it.
     */
    private function withMemoryLimit(callable $render): void
    {
        $limit = (string) config('dashboard.reports.pdf_memory_limit', '512M');
        $previous = (string) ini_get('memory_limit');
        $raise = $previous !== '-1' && $this->bytes($limit) > $this->bytes($previous);

        if ($raise) {
            ini_set('memory_limit', $limit);
        }

        try {
            $render();
        } finally {
            gc_collect_cycles();

            // PHP refuses a limit below the memory the process has reserved, and
            // rarely returns it to the OS after a large render — then the raised
            // ceiling simply stays for this worker, and `queue:work --memory`
            // recycles the worker once the job ends.
            if ($raise && memory_get_usage(true) < $this->bytes($previous) * 0.8) {
                ini_set('memory_limit', $previous);
            }
        }
    }

    /** "512M" → bytes. */
    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr(trim($value), -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
