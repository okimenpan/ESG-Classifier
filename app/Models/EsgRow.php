<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EsgRow extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'report_date' => 'date',
    ];

    /** Full report text; falls back to the title for rows without content. */
    public function fullContent(): string
    {
        return (string) ($this->content ?? $this->title ?? '');
    }

    /** The first sentence of the report (max ~160 characters) for the dashboard table. */
    public function firstSentence(): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $this->fullContent()));
        if (preg_match('/^.{20,}?[.!?](?=\s|$)/u', $text, $m)) {
            $text = $m[0];
        }

        return Str::limit($text, 160, preserveWords: true);
    }

    public function isTruncated(): bool
    {
        return mb_strlen($this->firstSentence()) < mb_strlen(trim($this->fullContent()));
    }
}
