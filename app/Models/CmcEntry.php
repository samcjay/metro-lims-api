<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CmcEntry extends Model
{
    protected $fillable = [
        'worksheet_template_id', 'range_from', 'range_to',
        'unit', 'medium', 'cmc_value', 'cmc_formula',
    ];
}
