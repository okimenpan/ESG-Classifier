<?php

namespace App\Jobs;

use App\Models\EsgFile;
use App\Services\EsgClassifier;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Stage 2: classify one chunk of rows. Duplicate texts are classified only once
 * (deduplicated by hash + global cache), then sent to the AI in parallel.
 */
class ClassifyChunkJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 3600;

    public int $tries = 2;

    public function __construct(public int $fileId, public int $fromId, public int $toId) {}

    public function handle(EsgClassifier $classifier): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $rows = DB::table('esg_rows')
            ->where('esg_file_id', $this->fileId)
            ->whereBetween('id', [$this->fromId, $this->toId])
            ->whereNull('category')
            ->get(['id', 'text_hash', 'text']);

        if ($rows->isEmpty()) {
            return;
        }

        // 1) Take results from the cache
        $results = DB::table('esg_cache')
            ->whereIn('text_hash', $rows->pluck('text_hash')->unique()->all())
            ->get()
            ->mapWithKeys(fn ($c) => [$c->text_hash => ['category' => $c->category, 'reason' => $c->reason]])
            ->all();

        // 2) The rest -> AI (one request per unique text)
        $todo = $rows->whereNotIn('text_hash', array_keys($results))
            ->unique('text_hash')
            ->pluck('text', 'text_hash')
            ->all();

        if ($todo) {
            $fresh = $classifier->classifyMany($todo);
            $now = now();
            foreach (array_chunk($fresh, 200, true) as $part) {
                DB::table('esg_cache')->insertOrIgnore(array_map(
                    fn ($hash, $r) => ['text_hash' => $hash, 'category' => $r['category'], 'reason' => $r['reason'], 'created_at' => $now],
                    array_keys($part), $part
                ));
            }
            $results += $fresh;
        }

        // 3) Save results per row
        $done = 0;
        DB::transaction(function () use ($rows, $results, &$done) {
            foreach ($rows as $row) {
                if ($r = $results[$row->text_hash] ?? null) {
                    DB::table('esg_rows')->where('id', $row->id)
                        ->update(['category' => $r['category'], 'reason' => $r['reason']]);
                    $done++;
                }
            }
        });

        EsgFile::whereKey($this->fileId)->incrementEach([
            'processed_rows' => $done,
            'failed_rows' => $rows->count() - $done,
        ]);
    }
}
