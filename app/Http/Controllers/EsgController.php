<?php

namespace App\Http\Controllers;

use App\Jobs\ImportReportsJob;
use App\Jobs\StartClassification;
use App\Models\EsgFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EsgController extends Controller
{
    public function index()
    {
        return view('esg.index', ['files' => EsgFile::latest()->limit(50)->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,csv'],
        ]);

        $upload = $request->file('file');
        $file = EsgFile::create([
            'original_name' => $upload->getClientOriginalName(),
            'path' => $upload->store('uploads'),
        ]);

        ImportReportsJob::dispatch($file->id);

        return redirect()->route('esg.index')->with('ok', 'File diterima, proses berjalan di background.');
    }

    public function status()
    {
        return EsgFile::latest()->limit(50)->get()->map(fn (EsgFile $f) => [
            'id' => $f->id,
            'status' => $f->status,
            'total' => $f->total_rows,
            'processed' => $f->processed_rows,
            'failed' => $f->failed_rows,
            'percent' => $f->progressPercent(),
            'error' => $f->error,
            'eta' => $this->eta($f),
            'download' => $f->hasDownload() ? route('esg.download', $f) : null,
        ]);
    }

    public function summary(EsgFile $file)
    {
        return $file->rows()->select('category', DB::raw('count(*) as total'))
            ->groupBy('category')->pluck('total', 'category');
    }

    public function download(EsgFile $file)
    {
        abort_unless($file->hasDownload(), 404, 'File hasil belum tersedia.');

        // BinaryFileResponse streams in small chunks; Storage::download() uses fpassthru,
        // which loads the whole file into memory (fails for result files > memory_limit)
        return response()->download(Storage::path($file->output_path), basename($file->output_path));
    }

    /** Resume: process again only the rows that have no category yet. */
    public function resume(EsgFile $file)
    {
        // While still running, the jobs remain in the queue (they continue once the worker is restarted)
        abort_if($file->isRunning(), 409, 'File masih diproses.');
        $file->total_rows > 0 && $file->header_row
            ? StartClassification::run($file)
            : ImportReportsJob::dispatch($file->id);

        return back()->with('ok', "Melanjutkan proses file #{$file->id}.");
    }

    public function destroy(EsgFile $file)
    {
        if ($file->batch_id) {
            Bus::findBatch($file->batch_id)?->cancel();
        }
        Storage::delete(array_filter([$file->path, $file->output_path]));
        $file->delete();

        return back()->with('ok', 'File dihapus.');
    }

    private function eta(EsgFile $f): ?string
    {
        if ($f->status !== 'classifying' || ! $f->started_at || $f->processed_rows < 1) {
            return null;
        }
        $elapsed = $f->started_at->diffInSeconds(now());
        $rate = $f->processed_rows / max(1, $elapsed);
        $left = ($f->total_rows - $f->processed_rows) / max($rate, 0.001);

        return gmdate($left >= 86400 ? 'j \h\a\r\i H:i:s' : 'H:i:s', (int) $left).' ('.round($rate, 1).' baris/detik)';
    }
}
