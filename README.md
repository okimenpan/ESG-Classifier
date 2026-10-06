# ESG Classifier

Classifies SP4N-LAPOR reports (Excel) into **Environment / Social / Governance** using an AI model (OpenAI-compatible).
Output: the original Excel + the `Kategori ESG` and `Alasan ESG` columns.

## Running

```bash
php artisan serve                                  # web UI at http://127.0.0.1:8000
php artisan queue:work --timeout=3600 --memory=1024 # worker (required; you can run 2-3 in parallel)
```

Very large file (bypasses the upload limit):

```bash
php artisan esg:import /path/laporan.xlsx
php artisan esg:status
```

Dashboard (list of reports + status × ESG category statistics): `http://127.0.0.1:8000/dashboard`.
Files imported before the dashboard columns existed: `php artisan esg:backfill` (re-reads the original Excel, keeps ESG results).

## Efficiency

1. **Streaming** (OpenSpout): the Excel file is read and written row by row, so RAM use stays low.
2. **Batching**: `ESG_BATCH_SIZE` reports per AI request.
3. **Parallel**: `ESG_CONCURRENCY` requests at once per worker (`Http::pool`).
4. **Deduplication + cache**: identical texts are sent to the AI only once (table `esg_cache`, persists across files).
5. **Chunked queue + resume**: if something fails, the "Lanjutkan" button processes only rows without a category.
6. Qwen3 *thinking* mode is turned off (`enable_thinking: false`) so responses are fast.

## Configuration

`config/esg.php`: text columns, header marker (`Tracking ID`), and the E/S/G category definitions (edit to match your framework).
The cache is keyed only by text, so after changing the definitions run `php artisan tinker --execute="DB::table('esg_cache')->truncate();"`.
