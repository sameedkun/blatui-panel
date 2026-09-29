<?php

namespace App\Support\Dashboard\Reports\Writers;

use App\Support\Dashboard\Reports\ReportDocument;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Excel workbook via OpenSpout, which streams rows straight to disk — memory
 * stays flat however large the report. A "Report" sheet holds the data (bold,
 * frozen, filterable heading row; real numeric cells; columns sized to their
 * content) and a "Summary" sheet the report's context and headline figures.
 */
class XlsxWriter extends ReportWriter
{
    /** Rows sampled to size the columns before streaming begins. */
    private const int SAMPLE_ROWS = 200;

    public function write(ReportDocument $document, string $path): int
    {
        $keys = array_keys($document->columns);
        $headings = array_values($document->columns);
        [$sample, $rest] = $this->sample($document->rows, self::SAMPLE_ROWS);

        $writer = new Writer;
        $writer->openToFile($path);
        $count = 0;

        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName($this->sheetName(__('dashboard.reports.sheets.data')));
            $sheet->setSheetView((new SheetView)->withFreezeRow(2));

            foreach ($this->columnWidths($headings, $keys, $sample) as $index => $width) {
                $sheet->setColumnWidth($width, $index + 1);
            }

            $writer->addRow(Row::fromValuesWithStyle($headings, $this->headingStyle()));

            foreach ([$sample, $rest] as $rows) {
                foreach ($rows as $row) {
                    $writer->addRow(Row::fromValues(array_map(fn (string $key): int|float|string|null => $this->value($row[$key] ?? null), $keys)));
                    $count++;
                }
            }

            if ($count > 0) {
                $sheet->setAutoFilter(new AutoFilter(0, 1, count($keys) - 1, $count + 1));
            }

            $summary = $writer->addNewSheetAndMakeItCurrent();
            $summary->setName($this->sheetName(__('dashboard.reports.sheets.summary')));
            $summary->setColumnWidth(30, 1);
            $summary->setColumnWidth(50, 2);

            $writer->addRow(Row::fromValuesWithStyle([$document->title], $this->headingStyle()->withFontSize(14)));

            foreach ([...$document->meta, ...$document->summary] as $label => $value) {
                $writer->addRow(Row::fromValues([$label, $value]));
            }
        } finally {
            $writer->close();
        }

        return $count;
    }

    private function headingStyle(): Style
    {
        return (new Style)->withFontBold(true)->withBackgroundColor('EEF0F4');
    }

    /** Cell value: numbers stay numeric, text is stripped of characters XML 1.0 can't hold. */
    private function value(int|float|string|null $value): int|float|string|null
    {
        return is_string($value)
            ? (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value)
            : $value;
    }

    /**
     * Width per column from the heading and the sampled values, clamped to a
     * readable range (Excel widths are roughly "characters").
     *
     * @param  list<string>  $headings
     * @param  list<string>  $keys
     * @param  list<array<string, int|float|string|null>>  $sample
     * @return list<float>
     */
    private function columnWidths(array $headings, array $keys, array $sample): array
    {
        return array_map(function (string $key, string $heading) use ($sample): float {
            $widest = mb_strlen($heading);

            foreach ($sample as $row) {
                $widest = max($widest, mb_strlen($this->cellText($row[$key] ?? null)));
            }

            return (float) max(10, min(60, $widest + 3));
        }, $keys, $headings);
    }

    private function sheetName(string $name): string
    {
        return mb_substr((string) preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name), 0, 31);
    }
}
