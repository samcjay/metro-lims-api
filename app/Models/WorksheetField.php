<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorksheetField extends Model
{
    protected $fillable = [
        'worksheet_template_id', 'key', 'label', 'type',
        'unit', 'decimal_places', 'options', 'required',
        'repeats_per_test_point', 'sort_order', 'group',
    ];

    protected $casts = [
        'options'                => 'array',
        'required'               => 'boolean',
        'repeats_per_test_point' => 'boolean',
    ];
}
