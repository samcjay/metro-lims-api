<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FormulaNode extends Model
{
    protected $fillable = [
        'worksheet_template_id', 'key', 'label', 'symbol',
        'uncertainty_type', 'formula', 'depends_on',
        'divisor_formula', 'sensitivity_coeff',
        'is_output', 'scope_per_test_point', 'sort_order',
    ];

    protected $casts = [
        'depends_on'           => 'array',
        'is_output'            => 'boolean',
        'scope_per_test_point' => 'boolean',
        'sensitivity_coeff'    => 'float',
    ];
}
