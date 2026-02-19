<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    /** @use HasFactory<\Database\Factories\SyncRunFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'resource',
        'status',
        'started_at',
        'finished_at',
        'records_processed',
        'errors_count',
        'checkpoint_updated_since',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'records_processed' => 'integer',
            'errors_count' => 'integer',
            'checkpoint_updated_since' => 'datetime',
            'meta' => 'array',
        ];
    }
}
