<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpRecordPhoto extends Model
{
    protected $fillable = [
        'follow_up_record_id',
        'uploaded_by',
        'original_name',
        'file_path',
        'mime_type',
        'size',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(FollowUpRecord::class, 'follow_up_record_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
