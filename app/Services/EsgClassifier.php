<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EsgClassifier
{
    /**
     * Classify many texts at once.
     *
     * @param  array<string, string>  $texts  [key => text]
     * @return array<string, array{category: string, reason: string}>  only successfully classified keys
     */
    public function classifyMany(array $texts): array
    {
        $batches = array_chunk($texts, max(1, config('esg.batch_size')), true);
        $results = [];

        $responses = Http::pool(function (Pool $pool) use ($batches) {
            foreach ($batches as $i => $batch) {
                $this->request($pool->as((string) $i), $batch);
            }
        }, config('esg.concurrency'));

        $missing = [];
        foreach ($batches as $i => $batch) {
            $parsed = $this->parse($responses[(string) $i] ?? null, $batch);
            $results += $parsed;
            $missing += array_diff_key($batch, $parsed);
        }

        // Retry the failed/missing ones one by one (usually caused by malformed JSON in a large batch)
        if ($missing) {
            $responses = Http::pool(function (Pool $pool) use ($missing) {
                foreach ($missing as $key => $text) {
                    $this->request($pool->as((string) $key), [$key => $text]);
                }
            }, config('esg.concurrency'));

            foreach ($missing as $key => $text) {
                $results += $this->parse($responses[(string) $key] ?? null, [$key => $text]);
            }
        }

        return $results;
    }

    private function request($pending, array $batch)
    {
        // Short ids (1..n) save tokens & are easy for the model to echo back
        $items = [];
        $n = 1;
        foreach ($batch as $text) {
            $items[] = ['id' => $n++, 'teks' => $text];
        }

        return $pending
            ->withToken(config('esg.ai.api_key'))
            ->timeout(config('esg.ai.timeout'))
            ->retry(config('esg.ai.retries'), 2000, throw: false)
            ->post($this->url(), [
                'model' => config('esg.ai.model'),
                'temperature' => 0,
                'max_tokens' => 120 * count($batch) + 100,
                // Qwen3: disable "thinking" mode -> much faster
                'chat_template_kwargs' => ['enable_thinking' => false],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => json_encode($items, JSON_UNESCAPED_UNICODE)],
                ],
            ]);
    }

    private function parse($response, array $batch): array
    {
        if (! $response instanceof Response || ! $response->successful()) {
            Log::warning('ESG AI request failed', [
                'error' => $response instanceof \Throwable ? $response->getMessage() : $response?->body(),
            ]);

            return [];
        }

        $content = (string) $response->json('choices.0.message.content');
        // Strip <think>...</think> and ```json fences if present
        $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $content = $m[0];
        }
        $data = json_decode($content, true);

        $keys = array_keys($batch);
        $categories = config('esg.categories');
        $results = [];

        foreach ($data['hasil'] ?? [] as $item) {
            $idx = (int) ($item['id'] ?? 0) - 1;
            $code = strtoupper(substr(trim((string) ($item['kategori'] ?? '')), 0, 1));
            if (! isset($keys[$idx], $categories[$code])) {
                continue;
            }
            $results[$keys[$idx]] = [
                'category' => $categories[$code],
                'reason' => mb_substr(trim((string) ($item['alasan'] ?? '')), 0, 490),
            ];
        }

        return $results;
    }

    private function url(): string
    {
        $base = rtrim(config('esg.ai.base_url'), '/');
        if (! str_starts_with($base, 'http')) {
            $base = 'http://'.$base;
        }

        return $base.'/v1/chat/completions';
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
Anda adalah analis ESG yang mengklasifikasikan laporan/pengaduan masyarakat kepada pemerintah (SP4N-LAPOR).
Klasifikasikan SETIAP laporan ke tepat SATU kategori:

{$this->definitions()}

Aturan:
- Pilih kategori yang paling dominan dari substansi masalah yang dilaporkan.
- Jika laporan mengeluhkan perilaku/prosedur aparat (pungli, lambat, tidak responsif), pilih G.
- Jika tidak jelas, pilih kategori yang paling mendekati; jangan pernah kosong.
- "alasan" maksimal 12 kata, bahasa Indonesia.

Input berupa array JSON [{"id":..,"teks":..}]. Jawab HANYA JSON valid tanpa teks lain:
{"hasil":[{"id":1,"kategori":"E|S|G","alasan":"..."}]}
PROMPT;
    }

    private function definitions(): string
    {
        return config('esg.definitions');
    }
}
