<?php

namespace App\Jobs;

use App\Models\EsgFile;
use App\Services\ExcelHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Stage 1: read Excel by streaming, store the classification text + the dashboard columns into the DB
 * (the other columns are not stored — the output is built by re-reading the original file).
 */
class ImportReportsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $fileId) {}

    public function handle(): void
    {
        $file = EsgFile::findOrFail($this->fileId);
        $file->update(['status' => 'importing', 'started_at' => now(), 'error' => null]);
        $file->rows()->delete();

        $reader = ExcelHelper::reader(Storage::path($file->path));
        $header = null;
        $indexes = [];
        $reportIndexes = [];
        $buffer = [];
        $total = 0;

        foreach (ExcelHelper::rows($reader) as $rowNumber => $values) {
            if ($header === null) {
                if ($rowNumber > 50) {
                    break;
                }
                if (ExcelHelper::isHeader($values)) {
                    $header = $values;
                    $indexes = ExcelHelper::textColumnIndexes($header);
                    $reportIndexes = ExcelHelper::reportColumnIndexes($header);
                    if (! $indexes) {
                        throw new \RuntimeException('Kolom teks laporan (Judul/Isi Laporan) tidak ditemukan pada header.');
                    }
                    $file->update(['header_row' => $rowNumber]);
                }

                continue;
            }

            $text = ExcelHelper::buildText($values, $header, $indexes);
            if ($text === '') {
                continue; // empty row
            }

            $buffer[] = [
                'esg_file_id' => $file->id,
                'row_number' => $rowNumber,
                'text_hash' => sha1(mb_strtolower($text)),
                'text' => $text,
                ...ExcelHelper::reportFields($values, $reportIndexes),
            ];
            $total++;

            if (count($buffer) >= 1000) {
                DB::table('esg_rows')->insert($buffer);
                $buffer = [];
                $file->update(['total_rows' => $total]);
            }
        }
        if ($buffer) {
            DB::table('esg_rows')->insert($buffer);
        }
        $reader->close();

        if ($header === null) {
            throw new \RuntimeException('Baris header ("'.config('esg.header_marker').'") tidak ditemukan.');
        }

        $file->update(['total_rows' => $total]);

        StartClassification::run($file);
    }

    public function failed(\Throwable $e): void
    {
        EsgFile::whereKey($this->fileId)->update(['status' => 'failed', 'error' => $e->getMessage()]);
    }
}
