<?php

namespace App\Jobs;

use App\Models\EsgFile;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

/**
 * Split the not-yet-classified rows into chunks -> one batch of jobs -> export when done.
 * Also used for "Resume" (only rows with an empty category are processed).
 */
class StartClassification
{
    public static function run(EsgFile $file): void
    {
        $chunk = max(1, config('esg.chunk_size'));
        $jobs = [];

        $file->rows()->whereNull('category')->select('id')->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$jobs, $file) {
                $jobs[] = new ClassifyChunkJob($file->id, $rows->first()->id, $rows->last()->id);
            });

        $file->update([
            'status' => 'classifying',
            'processed_rows' => $file->rows()->whereNotNull('category')->count(),
            'failed_rows' => 0,
        ]);

        if (! $jobs) {
            ExportResultJob::dispatch($file->id);

            return;
        }

        $fileId = $file->id;
        $batch = Bus::batch($jobs)
            ->name('esg-file-'.$fileId)
            ->allowFailures()
            ->finally(function (Batch $batch) use ($fileId) {
                ExportResultJob::dispatch($fileId);
            })
            ->dispatch();

        $file->update(['batch_id' => $batch->id]);
    }
}
