<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ScanJobModule extends Model
{
    use HasUuids;

    protected $table = 'scan_job_modules';

    protected $fillable = [
        'scan_job_id',
        'scan_module_id',
        'status',
        'duration_ms',
    ];
}
