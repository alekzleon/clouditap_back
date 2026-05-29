<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJobStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'print_job_id',
        'user_id',
        'from_status',
        'to_status',
        'note',
    ];

    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
