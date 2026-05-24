<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterInstrument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lab_id', 'description', 'make', 'model', 'serial_no',
        'instrument_type', 'range_from', 'range_to', 'unit',
        'accuracy_pct_fs', 'mpe', 'resolution_positive', 'resolution_negative',
        'accountable_drift', 'instrument_params',
        'poly_coefficients', 'poly_uncertainty_coefficients',
        'max_correction_residual', 'max_uncertainty_residual',
        'cal_certificate_no', 'cal_authority',
        'last_calibrated_at', 'next_due_at', 'cal_frequency_years', 'is_active',
    ];

    protected $casts = [
        'poly_coefficients'             => 'array',
        'poly_uncertainty_coefficients' => 'array',
        'last_calibrated_at'            => 'date',
        'next_due_at'                   => 'date',
        'is_active'                     => 'boolean',
        'mpe'                           => 'float',
        'accountable_drift'             => 'float',
        'resolution_positive'           => 'float',
        'resolution_negative'           => 'float',
        'max_correction_residual'       => 'float',
        'max_uncertainty_residual'      => 'float',
        'instrument_params'             => 'array',
    ];

    public function isDueForCalibration(): bool
    {
        return $this->next_due_at && $this->next_due_at->isPast();
    }

    public function isDueSoon(int $days = 30): bool
    {
        return $this->next_due_at && $this->next_due_at->diffInDays(now()) <= $days;
    }
}
