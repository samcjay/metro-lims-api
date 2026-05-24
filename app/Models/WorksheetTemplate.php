<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorksheetTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'instrument_type', 'test_method_ref',
        'unit', 'max_test_points', 'cycles',
        'has_before_after', 'has_head_correction',
        'reading_schema', 'version', 'is_active', 'created_by',
    ];

    protected $casts = [
        'has_before_after'    => 'boolean',
        'has_head_correction' => 'boolean',
        'is_active'           => 'boolean',
        'reading_schema'      => 'array',
    ];

    public function fields()
    {
        return $this->hasMany(WorksheetField::class)->orderBy('sort_order');
    }

    public function formulaNodes()
    {
        return $this->hasMany(FormulaNode::class)->orderBy('sort_order');
    }

    public function cmcEntries()
    {
        return $this->hasMany(CmcEntry::class);
    }
}
