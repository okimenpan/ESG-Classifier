<?php

return [

    'ai' => [
        'base_url' => env('AI_BASE_URL', '192.168.66.85:4000'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'qwen3.6'),
        'timeout' => (int) env('AI_TIMEOUT', 180),
        'retries' => (int) env('AI_RETRIES', 3),
    ],

    // Number of reports per single AI request (fewer HTTP calls = faster)
    'batch_size' => (int) env('ESG_BATCH_SIZE', 10),

    // Number of AI requests sent in parallel per worker
    'concurrency' => (int) env('ESG_CONCURRENCY', 16),

    // Number of Excel rows per queue job
    'chunk_size' => (int) env('ESG_CHUNK_SIZE', 500),

    // Max characters of report text sent to the AI (truncated to save tokens)
    'max_text_length' => (int) env('ESG_MAX_TEXT', 1500),

    // A cell with this value marks the column header row
    'header_marker' => 'Tracking ID',

    // Columns used as the classification text. Alternatives are separated by "|":
    // exact match first, then prefix match (e.g. "Isi Laporan Awal" truncated in the screenshot).
    'text_columns' => [
        'Judul Laporan',
        'Isi Laporan Awal|Isi Laporan',
        'Kategori',
    ],

    // Report columns stored in the DB for the dashboard (same "|" matching as text_columns)
    'report_columns' => [
        'tracking_id' => 'Tracking ID',
        'report_date' => 'Tanggal Laporan Masuk|Tanggal Laporan',
        'title' => 'Judul Laporan',
        'content' => 'Isi Laporan Awal|Isi Laporan',
        'agency' => 'Instansi Induk|Instansi Tujuan',
        'agency_unit' => 'Instansi Terdisposisi',
        'report_status' => 'Status Laporan',
    ],

    // Names of the new columns appended to the output
    'output_columns' => ['Kategori ESG', 'Alasan ESG'],

    'categories' => [
        'E' => 'Environment',
        'S' => 'Social',
        'G' => 'Governance',
    ],

    // Category definitions for the prompt — edit to match your organization's ESG framework
    'definitions' => <<<'TXT'
E = Environment (Lingkungan): pencemaran air/udara/tanah, limbah, sampah & kebersihan, banjir, drainase,
    kerusakan hutan/lahan, alih fungsi lahan, tambang/galian ilegal, bencana alam, perubahan iklim,
    energi, ruang terbuka hijau, satwa, kebisingan, sumber daya air.
S = Social (Sosial): kesehatan, pendidikan, bantuan sosial, kemiskinan, ketenagakerjaan, perlindungan
    anak/perempuan/disabilitas, perumahan, infrastruktur & fasilitas publik (jalan, jembatan, pasar,
    penerangan), transportasi, keamanan & ketertiban masyarakat, harga & ketersediaan bahan pokok,
    konsumen, kependudukan/sosial budaya, keagamaan.
G = Governance (Tata Kelola): korupsi, pungutan liar, gratifikasi, penyalahgunaan wewenang,
    disiplin & kinerja ASN/aparat, maladministrasi, prosedur & lambatnya pelayanan administrasi
    (perizinan, dokumen), transparansi anggaran, pengadaan barang/jasa, regulasi/kebijakan,
    manajemen kepegawaian, pengelolaan keuangan & aset pemerintah, pemilu/netralitas.
TXT,
];
