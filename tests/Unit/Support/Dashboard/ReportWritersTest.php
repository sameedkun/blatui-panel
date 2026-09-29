<?php

namespace Tests\Unit\Support\Dashboard;

use App\Enum\ReportFormat;
use App\Support\Dashboard\Reports\ReportDocument;
use App\Support\Dashboard\Reports\Writers\CsvWriter;
use App\Support\Dashboard\Reports\Writers\PdfWriter;
use App\Support\Dashboard\Reports\Writers\ReportWriter;
use App\Support\Dashboard\Reports\Writers\XlsxWriter;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ReportWritersTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'writer-test-');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_each_format_resolves_its_writer(): void
    {
        $this->assertInstanceOf(CsvWriter::class, ReportWriter::for(ReportFormat::Csv));
        $this->assertInstanceOf(XlsxWriter::class, ReportWriter::for(ReportFormat::Xlsx));
        $this->assertInstanceOf(PdfWriter::class, ReportWriter::for(ReportFormat::Pdf));
    }

    public function test_csv_writes_a_bom_heading_row_and_neutralises_formulas(): void
    {
        $count = (new CsvWriter)->write($this->document([
            ['name' => 'Jane', 'amount' => 12.5],
            ['name' => '=HYPERLINK("http://evil")', 'amount' => -3],
        ]), $this->path);

        $contents = file_get_contents($this->path);

        $this->assertSame(2, $count);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents);

        $lines = array_map('str_getcsv', explode("\n", trim(substr($contents, 3))));

        $this->assertSame(['Name', 'Amount'], $lines[0]);
        $this->assertSame(['Jane', '12.5'], $lines[1]);
        $this->assertSame("'=HYPERLINK(\"http://evil\")", $lines[2][0]);
        $this->assertSame('-3', $lines[2][1], 'Numbers are never prefixed.');
    }

    public function test_xlsx_is_a_valid_workbook_with_numeric_cells_and_a_summary_sheet(): void
    {
        $count = (new XlsxWriter)->write($this->document([
            ['name' => 'Jane & <Co>', 'amount' => 12.5],
            ['name' => null, 'amount' => 7],
        ]), $this->path);

        $this->assertSame(2, $count);

        $reader = new Reader;
        $reader->open($this->path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }

        $reader->close();

        $this->assertSame(['Report', 'Summary'], array_keys($sheets));
        $this->assertSame(['Name', 'Amount'], $sheets['Report'][0]);
        $this->assertSame(['Jane & <Co>', 12.5], $sheets['Report'][1]);
        $this->assertSame(7, $sheets['Report'][2][1], 'Numbers stay numeric cells.');
        $this->assertSame('Revenue', $sheets['Summary'][0][0]);
        $this->assertContains(['Period', 'Sep 1 – Sep 30, 2026'], $sheets['Summary']);
    }

    public function test_pdf_renders_unicode_and_paginates(): void
    {
        $rows = array_map(fn (int $i): array => ['name' => "Müşteri {$i} — Ünïcode", 'amount' => $i * 1.5], range(1, 120));

        $count = (new PdfWriter)->write($this->document($rows), $this->path);
        $pdf = file_get_contents($this->path);

        $this->assertSame(120, $count);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertGreaterThan(1, $this->pageCount($pdf), 'Many rows spill onto several pages.');
    }

    public function test_pdf_puts_one_table_on_each_page(): void
    {
        $rows = array_map(fn (int $i): array => ['name' => "Row {$i}", 'amount' => $i], range(1, 14 + 24 * 3));

        (new PdfWriter)->write($this->document($rows), $this->path);

        $this->assertSame(4, $this->pageCount(file_get_contents($this->path)), 'First page + three full pages, no spill-over.');
    }

    /**
     * Regression: a wide User growth PDF exhausted a 128 MB queue worker inside
     * DOMPDF. A report at the default row cap must render starting from that
     * same 128 MB limit (the writer raises it while laying out).
     */
    public function test_a_maximum_size_pdf_renders_from_a_128mb_worker(): void
    {
        $previous = ini_get('memory_limit');
        $cap = (int) config('dashboard.reports.pdf_max_rows');

        $columns = ['id' => 'ID', 'name' => 'Name', 'email' => 'Email', 'type' => 'Type', 'status' => 'Status', 'verified' => 'Verified', 'registered_at' => 'Registered', 'last_login' => 'Last sign-in'];
        $rows = (function () use ($cap) {
            for ($i = 1; $i <= $cap + 50; $i++) {
                yield ['id' => '01K'.str_pad((string) $i, 23, '0', STR_PAD_LEFT), 'name' => "Customer number {$i}", 'email' => "customer.{$i}@example.com", 'type' => 'App user', 'status' => 'Active', 'verified' => 'Yes', 'registered_at' => '2026-09-01 10:00', 'last_login' => '2026-09-20 18:30'];
            }
        })();

        try {
            ini_set('memory_limit', '128M');
            $count = (new PdfWriter)->write(new ReportDocument('User growth', $columns, $rows), $this->path);
        } finally {
            ini_set('memory_limit', $previous);
        }

        $this->assertSame($cap, $count, 'Rows past the cap are left out (and the PDF says so).');
        $this->assertGreaterThan(20, $this->pageCount(file_get_contents($this->path)));
    }

    public function test_pdf_stops_at_its_row_limit(): void
    {
        config()->set('dashboard.reports.pdf_max_rows', 10);
        $rows = array_map(fn (int $i): array => ['name' => "Row {$i}", 'amount' => $i], range(1, 25));

        $this->assertSame(10, (new PdfWriter)->write($this->document($rows), $this->path));
    }

    public function test_pdf_handles_an_empty_dataset(): void
    {
        $this->assertSame(0, (new PdfWriter)->write($this->document([]), $this->path));
        $this->assertSame(1, $this->pageCount(file_get_contents($this->path)));
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page[^s]/', $pdf);
    }

    /** @param  list<array<string, int|float|string|null>>  $rows */
    private function document(array $rows): ReportDocument
    {
        return new ReportDocument(
            title: 'Revenue',
            columns: ['name' => 'Name', 'amount' => 'Amount'],
            rows: (function () use ($rows) {
                yield from $rows;
            })(),
            meta: ['Period' => 'Sep 1 – Sep 30, 2026'],
            summary: ['Revenue' => '$1,000.00'],
        );
    }
}
