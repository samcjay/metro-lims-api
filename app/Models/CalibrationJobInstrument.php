<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationJobInstrument extends Model
{
    protected $fillable = ['calibration_result_id', 'master_instrument_id', 'role', 'notes'];

    public function instrument()
    {
        return $this->belongsTo(MasterInstrument::class, 'master_instrument_id');
    }
}
