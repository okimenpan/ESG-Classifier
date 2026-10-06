<?php

namespace App\Console\Commands;

use App\Jobs\ImportReportsJob;
use App\Models\EsgFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * For very large files (bypasses the upload limit): copy the file to storage, then queue the job.
 *   php artisan esg:import /path/laporan.xlsx
 */
class EsgImport extends Command
{
    protected $signature = 'esg:import {path : Path ke file .xlsx/.csv}';

    protected $description = 'Import an Excel file of reports and classify its ESG category in the background';

    public function handle(): int
    {
        $src = $this->argument('path');
        if (! is_file($src)) {
            $this->error("File tidak ditemukan: $src");

            return self::FAILURE;
        }

        $rel = 'uploads/'.uniqid().'_'.basename($src);
        Storage::makeDirectory('uploads');
        copy($src, Storage::path($rel));

        $file = EsgFile::create(['original_name' => basename($src), 'path' => $rel]);
        ImportReportsJob::dispatch($file->id);

        $this->info("Queued as file #{$file->id}. Make sure the worker is running: php artisan queue:work --timeout=3600");
        $this->info('Track progress in the browser (php artisan serve) or with: php artisan esg:status');

        return self::SUCCESS;
    }
}
