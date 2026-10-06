<?php

namespace App\Http\Controllers;

use App\Models\EsgFile;
use App\Models\EsgRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'file' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $rows = $this->filtered($filters)
            ->select(['id', 'report_date', 'title', 'content', 'agency', 'agency_unit', 'report_status', 'category', 'reason'])
            ->orderByDesc('report_date')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('esg.dashboard', [
            'rows' => $rows,
            'stats' => $this->statusCategoryStats($filters),
            'filters' => $filters,
            'files' => EsgFile::latest()->get(['id', 'original_name']),
            'statuses' => EsgRow::query()->whereNotNull('report_status')->distinct()->orderBy('report_status')->pluck('report_status'),
            'categories' => array_values(config('esg.categories')),
        ]);
    }

    /**
     * Cross tabulation of report status x ESG category.
     *
     * @return array{categories: list<string>, rows: list<array{status: string, counts: array<string, int>, total: int}>, totals: array<string, int>, total: int}
     */
    private function statusCategoryStats(array $filters): array
    {
        $counts = $this->filtered($filters)
            ->select('report_status', 'category', DB::raw('count(*) as total'))
            ->groupBy('report_status', 'category')
            ->toBase()
            ->get();

        $categories = array_values(config('esg.categories'));
        if ($counts->contains(fn ($c) => $c->category === null)) {
            $categories[] = 'Belum';
        }

        $rows = [];
        $totals = array_fill_keys($categories, 0);
        foreach ($counts as $c) {
            $status = $c->report_status ?? '(Tanpa status)';
            $category = $c->category ?? 'Belum';
            $rows[$status] ??= ['status' => $status, 'counts' => array_fill_keys($categories, 0), 'total' => 0];
            $rows[$status]['counts'][$category] = ($rows[$status]['counts'][$category] ?? 0) + $c->total;
            $rows[$status]['total'] += $c->total;
            $totals[$category] = ($totals[$category] ?? 0) + $c->total;
        }

        $rows = collect($rows)->sortByDesc('total')->values()->all();

        return ['categories' => $categories, 'rows' => $rows, 'totals' => $totals, 'total' => array_sum($totals)];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filtered(array $filters): Builder
    {
        return EsgRow::query()
            ->when($filters['file'] ?? null, fn (Builder $q, $file) => $q->where('esg_file_id', $file))
            ->when($filters['category'] ?? null, fn (Builder $q, $category) => $category === 'Belum'
                ? $q->whereNull('category')
                : $q->where('category', $category))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('report_status', $status))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->where('report_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->where('report_date', '<=', $to))
            ->when($filters['q'] ?? null, fn (Builder $q, $search) => $q->where(fn (Builder $q) => $q
                ->where('content', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('agency', 'like', "%{$search}%")
                ->orWhere('agency_unit', 'like', "%{$search}%")));
    }
}
