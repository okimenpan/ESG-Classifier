<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ESG Classifier</title>
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --text:#1f2933; --muted:#6b7280; --line:#e5e7eb; --accent:#0f766e;
                --e:#16a34a; --s:#2563eb; --g:#9333ea; --err:#dc2626; }
        * { box-sizing: border-box; }
        body { margin:0; font:14px/1.5 system-ui, -apple-system, Segoe UI, sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width:1100px; margin:0 auto; padding:24px 16px; }
        h1 { font-size:22px; margin:0 0 4px; }
        .muted { color:var(--muted); }
        .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:18px; margin-top:16px; }
        .flash { background:#ecfdf5; border:1px solid #a7f3d0; padding:10px 14px; border-radius:8px; margin-top:16px; }
        .error { background:#fef2f2; border-color:#fecaca; color:var(--err); }
        form.upload { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        button, .btn { background:var(--accent); color:#fff; border:0; border-radius:6px; padding:8px 14px; cursor:pointer; font:inherit; text-decoration:none; display:inline-block; }
        .btn.light { background:#fff; color:var(--text); border:1px solid var(--line); }
        .btn.danger { background:#fff; color:var(--err); border:1px solid #fecaca; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); }
        .bar { height:8px; background:var(--line); border-radius:4px; overflow:hidden; min-width:140px; }
        .bar > div { height:100%; background:var(--accent); transition:width .5s; }
        .badge { display:inline-block; padding:1px 8px; border-radius:10px; font-size:12px; background:var(--line); }
        .badge.done { background:#dcfce7; color:#166534; } .badge.failed { background:#fee2e2; color:#991b1b; }
        .chips span { display:inline-block; margin:4px 6px 0 0; font-size:12px; }
        .dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; }
        .actions { display:flex; gap:6px; flex-wrap:wrap; }
        .table-wrap { overflow-x:auto; }
    </style>
</head>
<body>
<div class="wrap">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div>
            <h1>ESG Classifier</h1>
            <div class="muted">Klasifikasi laporan ke kategori <b>Environment</b>, <b>Social</b>, atau <b>Governance</b> menggunakan AI ({{ config('esg.ai.model') }}).</div>
        </div>
        <a class="btn" href="{{ route('esg.dashboard') }}">Dashboard &rarr;</a>
    </div>

    @if (session('ok'))<div class="flash">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif

    <div class="card">
        <form class="upload" method="post" action="{{ route('esg.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="file" name="file" accept=".xlsx,.csv" required>
            <button type="submit">Upload &amp; Proses</button>
        </form>
        <div class="muted" style="margin-top:8px">
            Format .xlsx / .csv. File sangat besar? Gunakan CLI: <code>php artisan esg:import /path/file.xlsx</code>
        </div>
    </div>

    <div class="card table-wrap">
        <table>
            <thead><tr><th>#</th><th>File</th><th>Status</th><th>Progres</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse ($files as $f)
                <tr data-id="{{ $f->id }}">
                    <td>{{ $f->id }}</td>
                    <td>{{ $f->original_name }}<div class="muted">{{ $f->created_at->format('d M Y H:i') }}</div>
                        <div class="chips" data-summary></div></td>
                    <td><span class="badge {{ $f->status }}" data-status>{{ $f->status }}</span>
                        <div class="muted" data-error style="color:var(--err)">{{ $f->error }}</div></td>
                    <td>
                        <div class="bar"><div data-bar style="width:{{ $f->progressPercent() }}%"></div></div>
                        <div class="muted" data-progress>{{ number_format($f->processed_rows) }} / {{ number_format($f->total_rows) }} ({{ $f->progressPercent() }}%)</div>
                        <div class="muted" data-eta></div>
                    </td>
                    <td class="actions">
                        <a class="btn" data-download href="{{ $f->hasDownload() ? route('esg.download', $f) : '#' }}" style="{{ $f->hasDownload() ? '' : 'display:none' }}">Download</a>
                        <form method="post" action="{{ route('esg.resume', $f) }}" data-resume style="{{ $f->isRunning() ? 'display:none' : '' }}">
                            @csrf <button class="btn light" title="Proses ulang baris yang belum terklasifikasi">Lanjutkan</button>
                        </form>
                        <form method="post" action="{{ route('esg.destroy', $f) }}" onsubmit="return confirm('Hapus file ini?')">
                            @csrf @method('delete') <button class="btn danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Belum ada file.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
const colors = {Environment:'var(--e)', Social:'var(--s)', Governance:'var(--g)'};
const fmt = n => Number(n).toLocaleString('id-ID');
const summaryLoaded = new Set();

async function loadSummary(tr, id) {
    const data = await (await fetch(`/files/${id}/summary`)).json();
    tr.querySelector('[data-summary]').innerHTML = Object.entries(data).map(([k, v]) =>
        `<span><i class="dot" style="background:${colors[k] || 'var(--muted)'}"></i>${k === '' ? 'Belum terklasifikasi' : k}: ${fmt(v)}</span>`).join('');
}

async function poll() {
    try {
        const files = await (await fetch('{{ route('esg.status') }}')).json();
        for (const f of files) {
            const tr = document.querySelector(`tr[data-id="${f.id}"]`);
            if (!tr) continue;
            const running = ['pending','importing','classifying','exporting'].includes(f.status);
            const st = tr.querySelector('[data-status]');
            st.textContent = f.status; st.className = 'badge ' + f.status;
            tr.querySelector('[data-bar]').style.width = f.percent + '%';
            tr.querySelector('[data-progress]').textContent =
                `${fmt(f.processed)} / ${fmt(f.total)} (${f.percent}%)` + (f.failed ? ` · gagal ${fmt(f.failed)}` : '');
            tr.querySelector('[data-eta]').textContent = f.eta ? 'Sisa ± ' + f.eta : '';
            tr.querySelector('[data-error]').textContent = f.error || '';
            tr.querySelector('[data-resume]').style.display = running ? 'none' : '';
            const dl = tr.querySelector('[data-download]');
            if (f.download) { dl.href = f.download; dl.style.display = ''; } else { dl.style.display = 'none'; }
            if (!running && !summaryLoaded.has(f.id) && f.total) { summaryLoaded.add(f.id); loadSummary(tr, f.id); }
        }
    } catch (e) {}
    setTimeout(poll, 3000);
}
poll();
</script>
</body>
</html>
