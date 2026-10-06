<?php

namespace App\Console\Commands;

use App\Models\EsgFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EsgStatus extends Command
{
    protected $signature = 'esg:status';

    protected $description = 'Show the progress of ESG classification files';

    public function handle(): void
    {
        $this->table(
            ['ID', 'File', 'Status', 'Progress', 'Failed', 'Output'],
            EsgFile::latest()->limit(20)->get()->map(fn ($f) => [
                $f->id, $f->original_name, $f->status,
                "{$f->processed_rows}/{$f->total_rows} ({$f->progressPercent()}%)",
                $f->failed_rows,
                $f->output_path ? Storage::path($f->output_path) : ($f->error ?? '-'),
            ])
        );
    }
}
