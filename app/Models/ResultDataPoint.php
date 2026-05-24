<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ResultDataPoint extends Model
{
    protected $fillable = [
        'calibration_result_id', 'test_point_index', 'nominal_value',
        'uuc_mean', 'ref_mean', 'error_of_indication',
        'hysteresis', 'repeatability',
        'u_combined', 'u_expanded', 'cmc_value', 'u_final', 'u_source',
        'within_mpe', 'budget_components',
    ];

    protected $casts = [
        'budget_components' => 'array',
        'within_mpe'        => 'boolean',
    ];
}
