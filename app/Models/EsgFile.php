<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class EsgFile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(EsgRow::class);
    }

    public function progressPercent(): float
    {
        return $this->total_rows > 0
            ? round(min(100, $this->processed_rows / $this->total_rows * 100), 1)
            : 0;
    }

    /** The result file is complete (export finished) and not empty. */
    public function hasDownload(): bool
    {
        return $this->status === 'done'
            && $this->output_path
            && Storage::exists($this->output_path)
            && Storage::size($this->output_path) > 0;
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'importing', 'classifying', 'exporting']);
    }
}
