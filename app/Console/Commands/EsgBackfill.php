<?php

namespace App\Console\Commands;

use App\Models\EsgFile;
use App\Services\ExcelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Fill the dashboard columns (date, content, agency, status) of rows imported before
 * those columns existed, by re-reading the original file. ESG results are not touched.
 *   php artisan esg:backfill        (all files)
 *   php artisan esg:backfill 2      (one file)
 */
class EsgBackfill extends Command
{
    protected $signature = 'esg:backfill {file? : ID file (kosong = semua file)}';

    protected $description = 'Fill the report columns of already imported rows from the original Excel file';

    public function handle(): int
    {
        $files = EsgFile::query()
            ->when($this->argument('file'), fn ($q, $id) => $q->whereKey($id))
            ->whereNotNull('header_row')
            ->get();

        foreach ($files as $file) {
            if (! Storage::exists($file->path)) {
                $this->warn("#{$file->id} {$file->original_name}: file asli tidak ditemukan, dilewati.");

                continue;
            }

            $this->info("#{$file->id} {$file->original_name}");
            $updated = $this->backfill($file);
            $this->line("  {$updated} baris diperbarui.");
        }

        return self::SUCCESS;
    }

    private function backfill(EsgFile $file): int
    {
        $reader = ExcelHelper::reader(Storage::path($file->path));
        $indexes = [];
        $buffer = [];
        $updated = 0;
        $bar = $this->output->createProgressBar($file->total_rows);

        foreach (ExcelHelper::rows($reader) as $rowNumber => $values) {
            if ($rowNumber === $file->header_row) {
                $indexes = ExcelHelper::reportColumnIndexes($values);
            }
            if ($rowNumber <= $file->header_row) {
                continue;
            }

            $buffer[$rowNumber] = ExcelHelper::reportFields($values, $indexes);
            if (count($buffer) >= 1000) {
                $updated += $this->flush($file->id, $buffer);
                $bar->advance(count($buffer));
                $buffer = [];
            }
        }
        $updated += $this->flush($file->id, $buffer);
        $reader->close();
        $bar->finish();
        $this->newLine();

        return $updated;
    }

    /**
     * @param  array<int, array<string, string|null>>  $buffer  [row_number => fields]
     */
    private function flush(int $fileId, array $buffer): int
    {
        return DB::transaction(function () use ($fileId, $buffer) {
            $updated = 0;
            foreach ($buffer as $rowNumber => $fields) {
                $updated += DB::table('esg_rows')
                    ->where('esg_file_id', $fileId)
                    ->where('row_number', $rowNumber)
                    ->update($fields);
            }

            return $updated;
        });
    }
}
