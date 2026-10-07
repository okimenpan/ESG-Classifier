<?php

namespace App\Jobs;

use App\Models\EsgFile;
use App\Services\ExcelHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Stage 3: re-read the original file (streaming), copy all columns as they are,
 * append the "Kategori ESG" & "Alasan ESG" columns. Merge-join by row_number (both sorted).
 */
class ExportResultJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $fileId) {}

    public function handle(): void
    {
        $file = EsgFile::findOrFail($this->fileId);
        $file->update([
            'status' => 'exporting',
            'failed_rows' => $file->rows()->whereNull('category')->count(),
        ]);

        $outRel = 'results/'.pathinfo($file->original_name, PATHINFO_FILENAME).'_ESG_'.$file->id.'.xlsx';
        Storage::makeDirectory('results');

        $writer = new Writer;
        $writer->openToFile(Storage::path($outRel));
        $bold = (new Style)->withFontBold(true);

        $results = $file->rows()
            ->select(['id', 'row_number', 'category', 'reason'])
            ->lazyById(5000, 'id')
            ->getIterator();
        // lazyById sorts by id; ids are inserted in row_number order so the order is the same
        $current = $results->valid() ? $results->current() : null;

        $reader = ExcelHelper::reader(Storage::path($file->path));
        $width = 0;

        foreach (ExcelHelper::rows($reader) as $rowNumber => $values) {
            if ($rowNumber < $file->header_row) {
                $writer->addRow(Row::fromValues($values));

                continue;
            }

            if ($rowNumber === $file->header_row) {
                $width = count($values);
                $writer->addRow(Row::fromValuesWithStyle(
                    [...$values, ...config('esg.output_columns')], $bold
                ));

                continue;
            }

            while ($current && (int) $current->row_number < $rowNumber) {
                $results->next();
                $current = $results->valid() ? $results->current() : null;
            }

            $extra = ['', ''];
            if ($current && (int) $current->row_number === $rowNumber) {
                $extra = [$current->category ?? '', $current->reason ?? ''];
            }

            // pad so the new columns always sit right after the last header column
            $values = array_pad($values, $width, '');
            $writer->addRow(Row::fromValues([...$values, ...$extra]));
        }

        $reader->close();
        $writer->close();

        $file->update([
            'status' => 'done',
            'error' => null,
            'output_path' => $outRel,
            'finished_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        EsgFile::whereKey($this->fileId)->update(['status' => 'failed', 'error' => 'Export: '.$e->getMessage()]);
    }
}
