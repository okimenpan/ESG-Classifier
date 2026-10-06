<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard ESG</title>
    <style>
        :root { color-scheme: light; --bg:#f6f7f9; --card:#fff; --text:#1f2933; --muted:#6b7280; --line:#e5e7eb; --accent:#0f766e;
                --env:#1baf7a; --soc:#2a78d6; --gov:#eb6834; --none:#a3a29c; --track:#f0f1f3; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) { color-scheme: dark; --bg:#111214; --card:#1a1a19; --text:#f3f4f6; --muted:#a1a1aa; --line:#2e2e2c; --accent:#2dd4bf;
                --env:#199e70; --soc:#3987e5; --gov:#d95926; --none:#6b6a66; --track:#232322; }
        }
        :root[data-theme="dark"] { color-scheme: dark; --bg:#111214; --card:#1a1a19; --text:#f3f4f6; --muted:#a1a1aa; --line:#2e2e2c; --accent:#2dd4bf;
                --env:#199e70; --soc:#3987e5; --gov:#d95926; --none:#6b6a66; --track:#232322; }
        * { box-sizing: border-box; }
        body { margin:0; font:14px/1.5 system-ui, -apple-system, Segoe UI, sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width:1280px; margin:0 auto; padding:24px 16px; }
        h1 { font-size:22px; margin:0 0 4px; } h2 { font-size:16px; margin:0 0 12px; }
        .muted { color:var(--muted); }
        .top { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:18px; margin-top:16px; }
        a { color:var(--accent); }
        button, .btn { background:var(--accent); color:#fff; border:0; border-radius:6px; padding:8px 14px; cursor:pointer; font:inherit; text-decoration:none; display:inline-block; }
        .btn.light { background:transparent; color:var(--text); border:1px solid var(--line); }
        .link { background:none; border:0; padding:0; color:var(--accent); cursor:pointer; font:inherit; font-size:13px; }
        form.filters { display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; }
        form.filters label { display:flex; flex-direction:column; font-size:12px; color:var(--muted); gap:2px; }
        input, select { font:inherit; padding:7px 8px; border:1px solid var(--line); border-radius:6px; background:var(--card); color:var(--text); max-width:100%; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); white-space:nowrap; }
        td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .table-wrap { overflow-x:auto; }
        .badge { display:inline-flex; align-items:center; gap:5px; padding:1px 8px; border-radius:10px; font-size:12px; border:1px solid var(--line); white-space:nowrap; }
        .dot { display:inline-block; width:9px; height:9px; border-radius:50%; flex:none; }
        .tiles { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; }
        .tile { border:1px solid var(--line); border-radius:8px; padding:12px; }
        .tile .v { font-size:24px; font-weight:600; font-variant-numeric:tabular-nums; }
        .legend { display:flex; gap:14px; flex-wrap:wrap; font-size:13px; margin-bottom:10px; }
        .legend span { display:inline-flex; align-items:center; gap:6px; }
        .chart { display:grid; grid-template-columns:minmax(120px, 220px) 1fr auto; gap:8px 12px; align-items:center; }
        .chart .label { font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .chart .total { font-size:13px; color:var(--muted); font-variant-numeric:tabular-nums; }
        .stack { display:flex; gap:2px; height:16px; background:var(--track); border-radius:4px; }
        .seg { height:100%; min-width:2px; position:relative; cursor:default; }
        .seg:first-child { border-radius:4px 0 0 4px; } .seg:last-child { border-radius:0 4px 4px 0; } .seg:only-child { border-radius:4px; }
        .seg:hover { filter:brightness(1.12); }
        #tip { position:fixed; pointer-events:none; background:var(--card); color:var(--text); border:1px solid var(--line); border-radius:6px; padding:6px 10px; font-size:12px; box-shadow:0 4px 14px rgba(0,0,0,.12); display:none; z-index:10; }
        .content { max-width:520px; }
        .pagination { display:flex; flex-wrap:wrap; gap:4px; list-style:none; padding:0; margin:12px 0 0; }
        .pagination li > * { display:inline-block; padding:4px 10px; border:1px solid var(--line); border-radius:6px; text-decoration:none; }
        .pagination li.active > * { background:var(--accent); color:#fff; border-color:var(--accent); }
        .pagination li.disabled > * { color:var(--muted); }
        dialog { border:1px solid var(--line); border-radius:10px; padding:0; width:min(760px, calc(100vw - 32px)); background:var(--card); color:var(--text); }
        dialog::backdrop { background:rgba(0,0,0,.45); }
        dialog .head { padding:16px 18px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; gap:12px; }
        dialog .body { padding:16px 18px; max-height:65vh; overflow:auto; white-space:pre-wrap; }
        @media (max-width:640px) { .chart { grid-template-columns:1fr auto; } .chart .stack { grid-column:1 / -1; } }
    </style>
</head>
<body>
@php
    $colors = ['Environment' => 'var(--env)', 'Social' => 'var(--soc)', 'Governance' => 'var(--gov)', 'Belum' => 'var(--none)'];
    $fmt = fn ($n) => number_format($n, 0, ',', '.');
    $maxTotal = max(1, collect($stats['rows'])->max('total') ?? 1);
@endphp
<div class="wrap">
    <div class="top">
        <div>
            <h1>Dashboard ESG</h1>
            <div class="muted">Hasil klasifikasi laporan ke kategori Environment, Social, dan Governance.</div>
        </div>
        <a class="btn light" href="{{ route('esg.index') }}">&larr; Upload &amp; proses file</a>
    </div>

    <div class="card">
        <form class="filters" method="get" action="{{ route('esg.dashboard') }}">
            <label>File
                <select name="file">
                    <option value="">Semua file</option>
                    @foreach ($files as $f)
                        <option value="{{ $f->id }}" @selected(($filters['file'] ?? null) == $f->id)>#{{ $f->id }} {{ $f->original_name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Kategori ESG
                <select name="category">
                    <option value="">Semua</option>
                    @foreach ([...$categories, 'Belum'] as $c)
                        <option value="{{ $c }}" @selected(($filters['category'] ?? null) === $c)>{{ $c === 'Belum' ? 'Belum terklasifikasi' : $c }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status laporan
                <select name="status">
                    <option value="">Semua</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? null) === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </label>
            <label>Dari tanggal <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
            <label>Sampai tanggal <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
            <label>Cari <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Isi laporan / instansi"></label>
            <button type="submit">Terapkan</button>
            @if (array_filter($filters))<a class="btn light" href="{{ route('esg.dashboard') }}">Reset</a>@endif
        </form>
    </div>

    <div class="card">
        <h2>Statistik status laporan &times; kategori ESG</h2>
        <div class="tiles">
            <div class="tile"><div class="muted">Total laporan</div><div class="v">{{ $fmt($stats['total']) }}</div></div>
            @foreach ($stats['categories'] as $c)
                @php $n = $stats['totals'][$c] ?? 0; @endphp
                <div class="tile">
                    <div class="muted" style="display:flex;align-items:center;gap:6px"><i class="dot" style="background:{{ $colors[$c] }}"></i>{{ $c === 'Belum' ? 'Belum terklasifikasi' : $c }}</div>
                    <div class="v">{{ $fmt($n) }}</div>
                    <div class="muted">{{ $stats['total'] ? number_format($n / $stats['total'] * 100, 1, ',', '.') : 0 }}%</div>
                </div>
            @endforeach
        </div>

        @if ($stats['rows'])
            <div style="margin-top:20px">
                <div class="legend">
                    @foreach ($stats['categories'] as $c)
                        <span><i class="dot" style="background:{{ $colors[$c] }}"></i>{{ $c === 'Belum' ? 'Belum terklasifikasi' : $c }}</span>
                    @endforeach
                </div>
                <div class="chart">
                    @foreach ($stats['rows'] as $r)
                        <div class="label" title="{{ $r['status'] }}">{{ $r['status'] }}</div>
                        <div class="stack-wrap"><div class="stack" style="width:{{ max(1, $r['total'] / $maxTotal * 100) }}%">
                            @foreach ($stats['categories'] as $c)
                                @if ($r['counts'][$c] > 0)
                                    <div class="seg" style="flex:{{ $r['counts'][$c] }};background:{{ $colors[$c] }}"
                                         data-tip="<b>{{ e($r['status']) }}</b><br>{{ $c === 'Belum' ? 'Belum terklasifikasi' : $c }}: {{ $fmt($r['counts'][$c]) }} ({{ number_format($r['counts'][$c] / $r['total'] * 100, 1, ',', '.') }}%)"></div>
                                @endif
                            @endforeach
                        </div></div>
                        <div class="total">{{ $fmt($r['total']) }}</div>
                    @endforeach
                </div>
            </div>

            <div class="table-wrap" style="margin-top:20px">
                <table>
                    <thead>
                    <tr>
                        <th>Status laporan</th>
                        @foreach ($stats['categories'] as $c)<th class="num">{{ $c === 'Belum' ? 'Belum' : $c }}</th>@endforeach
                        <th class="num">Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($stats['rows'] as $r)
                        <tr>
                            <td>{{ $r['status'] }}</td>
                            @foreach ($stats['categories'] as $c)
                                <td class="num">{{ $fmt($r['counts'][$c]) }}
                                    <div class="muted" style="font-size:12px">{{ number_format($r['counts'][$c] / max(1, $r['total']) * 100, 1, ',', '.') }}%</div></td>
                            @endforeach
                            <td class="num"><b>{{ $fmt($r['total']) }}</b></td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><b>Total</b></td>
                        @foreach ($stats['categories'] as $c)<td class="num"><b>{{ $fmt($stats['totals'][$c] ?? 0) }}</b></td>@endforeach
                        <td class="num"><b>{{ $fmt($stats['total']) }}</b></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        @else
            <div class="muted" style="margin-top:12px">Tidak ada data.</div>
        @endif
    </div>

    <div class="card">
        <h2>Daftar laporan <span class="muted" style="font-weight:normal">({{ $fmt($rows->total()) }})</span></h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th class="num">No</th><th>Tanggal laporan</th><th>Isi laporan</th><th>Instansi tujuan</th>
                    <th>Status laporan</th><th>Kategori ESG</th><th>Alasan ESG</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $i => $row)
                    <tr>
                        <td class="num">{{ $fmt($rows->firstItem() + $i) }}</td>
                        <td style="white-space:nowrap">{{ $row->report_date?->format('d-m-Y') ?? '-' }}</td>
                        <td class="content">
                            {{ $row->firstSentence() ?: '-' }}
                            @if ($row->isTruncated())
                                <div><button type="button" class="link" data-open="full-{{ $row->id }}">Lihat selengkapnya</button></div>
                                <template id="full-{{ $row->id }}">
                                    <div class="head">
                                        <div>
                                            <b>{{ $row->title ?? 'Isi laporan' }}</b>
                                            <div class="muted">{{ $row->report_date?->format('d-m-Y') }} · {{ $row->agency_unit ?? $row->agency }}</div>
                                        </div>
                                        <button type="button" class="btn light" data-close>Tutup</button>
                                    </div>
                                    <div class="body">{{ $row->fullContent() }}</div>
                                </template>
                            @endif
                        </td>
                        <td>{{ $row->agency ?? '-' }}
                            @if ($row->agency_unit && $row->agency_unit !== $row->agency)<div class="muted">{{ $row->agency_unit }}</div>@endif</td>
                        <td>{{ $row->report_status ?? '-' }}</td>
                        <td>
                            @if ($row->category)
                                <span class="badge"><i class="dot" style="background:{{ $colors[$row->category] ?? 'var(--none)' }}"></i>{{ $row->category }}</span>
                            @else
                                <span class="muted">Belum</span>
                            @endif
                        </td>
                        <td>{{ $row->reason ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Tidak ada laporan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $rows->onEachSide(1)->links('pagination::bootstrap-3') }}
    </div>
</div>

<dialog id="full"></dialog>
<div id="tip"></div>

<script>
const dialog = document.getElementById('full');
document.addEventListener('click', e => {
    const open = e.target.closest('[data-open]');
    if (open) {
        dialog.replaceChildren(document.getElementById(open.dataset.open).content.cloneNode(true));
        dialog.showModal();
    }
    if (e.target.closest('[data-close]') || e.target === dialog) dialog.close();
});

const tip = document.getElementById('tip');
document.querySelectorAll('[data-tip]').forEach(seg => {
    seg.addEventListener('mousemove', e => {
        tip.innerHTML = seg.dataset.tip;
        tip.style.display = 'block';
        const x = Math.min(e.clientX + 14, window.innerWidth - tip.offsetWidth - 8);
        tip.style.left = x + 'px';
        tip.style.top = (e.clientY + 14) + 'px';
    });
    seg.addEventListener('mouseleave', () => tip.style.display = 'none');
});
</script>
</body>
</html>
