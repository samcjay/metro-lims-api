<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalibrationResult extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'calibration_job_id', 'worksheet_template_id', 'master_instrument_id',
        'uuc_description', 'uuc_make_model', 'uuc_serial_no',
        'uuc_instrument_id', 'uuc_range', 'uuc_unit',
        'status', 'field_values', 'result_snapshot', 'engine_version',
        'calculated_at', 'calculated_by',
        'metrologist_signature', 'signed_at', 'signed_by',
        'authorizer_signature', 'authorized_at', 'authorized_by',
    ];

    protected $casts = [
        'field_values'    => 'array',
        'result_snapshot' => 'array',
        'calculated_at'   => 'datetime',
        'signed_at'       => 'datetime',
        'authorized_at'   => 'datetime',
    ];

    public function isLocked(): bool
    {
        return !is_null($this->signed_at);
    }

    public function calibrationJob()
    {
        return $this->belongsTo(CalibrationJob::class);
    }

    public function template()
    {
        return $this->belongsTo(WorksheetTemplate::class, 'worksheet_template_id');
    }

    public function masterInstrument()
    {
        return $this->belongsTo(MasterInstrument::class);
    }

    public function dataPoints()
    {
        return $this->hasMany(ResultDataPoint::class)->orderBy('test_point_index');
    }

    public function jobInstruments()
    {
        return $this->hasMany(CalibrationJobInstrument::class)->with('instrument');
    }
}
