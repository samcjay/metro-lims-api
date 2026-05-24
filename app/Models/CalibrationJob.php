<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalibrationJob extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'job_no',
        'customer_name',
        'customer_ref',
        'date_received',
        'date_calibrated',
        'status',
        'assigned_to',
    ];

    protected $casts = [
        'date_received'   => 'date',
        'date_calibrated' => 'date',
    ];

    public function results()
    {
        return $this->hasMany(CalibrationResult::class);
    }

    public static function nextJobNo(): string
    {
        $year = now()->format('Y');
        $prefix = 'MCS-' . $year . '-';
        $last = static::withTrashed()->where('job_no', 'like', $prefix . '%')->max('job_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) : 0;
        return $prefix . str_pad($seq + 1, 4, '0', STR_PAD_LEFT);
    }
}
