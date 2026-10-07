<?php

namespace Tests\Feature;

use App\Models\EsgFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $status, ?string $content): EsgFile
    {
        Storage::fake();
        if ($content !== null) {
            Storage::put('results/laporan_ESG_1.xlsx', $content);
        }

        return EsgFile::create([
            'original_name' => 'laporan.xlsx',
            'path' => 'uploads/laporan.xlsx',
            'output_path' => 'results/laporan_ESG_1.xlsx',
            'status' => $status,
        ]);
    }

    public function test_finished_result_is_streamed_as_file_download(): void
    {
        $file = $this->file('done', 'xlsx-content');

        $response = $this->get(route('esg.download', $file))->assertOk();

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $response->assertDownload('laporan_ESG_1.xlsx');
    }

    public function test_empty_result_from_failed_export_is_not_downloadable(): void
    {
        $file = $this->file('failed', '');

        $this->get(route('esg.download', $file))->assertNotFound();
        $this->assertNull($this->getJson(route('esg.status'))->json('0.download'));
    }

    public function test_missing_result_file_is_not_downloadable(): void
    {
        $file = $this->file('done', null);

        $this->get(route('esg.download', $file))->assertNotFound();
    }
}
