<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportPhoto extends Model
{
    protected $fillable = [
        'report_month',
        'caption',
        'file_path',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'report_month' => 'date',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
