<?php

namespace Tests\Feature;

use App\Models\EsgFile;
use App\Services\ExcelHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function seedRows(): EsgFile
    {
        $file = EsgFile::create(['original_name' => 'laporan.xlsx', 'path' => 'uploads/laporan.xlsx']);
        $rows = [
            ['Terverifikasi', 'Environment', 'Sungai tercemar limbah pabrik. Warga sudah mengeluh sejak lama dan belum ada tindakan.'],
            ['Terverifikasi', 'Governance', 'Pungutan liar saat mengurus KTP di kantor kecamatan.'],
            ['Selesai', 'Social', 'Bantuan sosial tidak tepat sasaran.'],
            ['Selesai', null, 'Belum diklasifikasikan.'],
        ];
        foreach ($rows as $i => [$status, $category, $content]) {
            $file->rows()->create([
                'row_number' => $i + 12,
                'text_hash' => sha1($content),
                'text' => $content,
                'content' => $content,
                'report_date' => '2026-01-0'.($i + 1),
                'agency' => 'PEMERINTAH KABUPATEN TORAJA UTARA',
                'report_status' => $status,
                'category' => $category,
                'reason' => $category ? "Alasan {$category}" : null,
            ]);
        }

        return $file;
    }

    public function test_dashboard_shows_reports_with_excerpt_and_full_content(): void
    {
        $this->seedRows();

        $this->get(route('esg.dashboard'))
            ->assertOk()
            ->assertSee('Sungai tercemar limbah pabrik.')
            ->assertSee('Lihat selengkapnya')
            ->assertSee('Warga sudah mengeluh sejak lama dan belum ada tindakan.')
            ->assertSee('PEMERINTAH KABUPATEN TORAJA UTARA')
            ->assertSee('Alasan Governance')
            ->assertSee('04-01-2026');
    }

    public function test_status_by_category_statistics(): void
    {
        $this->seedRows();

        $stats = $this->get(route('esg.dashboard'))->assertOk()->viewData('stats');

        $this->assertSame(4, $stats['total']);
        $this->assertSame(['Environment', 'Social', 'Governance', 'Belum'], $stats['categories']);
        $byStatus = collect($stats['rows'])->keyBy('status');
        $this->assertSame(['Environment' => 1, 'Social' => 0, 'Governance' => 1, 'Belum' => 0], $byStatus['Terverifikasi']['counts']);
        $this->assertSame(['Environment' => 0, 'Social' => 1, 'Governance' => 0, 'Belum' => 1], $byStatus['Selesai']['counts']);
    }

    public function test_filters_apply_to_list_and_statistics(): void
    {
        $this->seedRows();

        $response = $this->get(route('esg.dashboard', ['status' => 'Terverifikasi', 'category' => 'Governance']))->assertOk();

        $this->assertSame(1, $response->viewData('rows')->total());
        $this->assertSame(1, $response->viewData('stats')['total']);
        $response->assertSee('Pungutan liar')->assertDontSee('Sungai tercemar');
    }

    public function test_report_fields_are_extracted_from_excel_row(): void
    {
        $header = ['Tracking ID', 'Tanggal Laporan Masuk', 'Judul Laporan', 'Isi Laporan Awal', 'Isi Laporan Akhir', 'Instansi Induk', 'Instansi Terdisposisi', 'Status Laporan'];
        $values = ['5049672', '6 Jan 2026', 'Judul', 'Isi awal', 'isi akhir', 'Kejaksaan RI', 'Kejati Sultra', 'Terverifikasi'];

        $fields = ExcelHelper::reportFields($values, ExcelHelper::reportColumnIndexes($header));

        $this->assertSame([
            'tracking_id' => '5049672',
            'report_date' => '2026-01-06',
            'title' => 'Judul',
            'content' => 'Isi awal',
            'agency' => 'Kejaksaan RI',
            'agency_unit' => 'Kejati Sultra',
            'report_status' => 'Terverifikasi',
        ], $fields);
    }
}
