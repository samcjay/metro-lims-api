<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_instruments', function (Blueprint $table) {
            $table->decimal('well_axial',    8, 4)->nullable()->after('accountable_drift');
            $table->decimal('well_radial',   8, 4)->nullable()->after('well_axial');
            $table->decimal('well_stability', 8, 4)->nullable()->after('well_radial');
        });
    }

    public function down(): void
    {
        Schema::table('master_instruments', function (Blueprint $table) {
            $table->dropColumn(['well_axial', 'well_radial', 'well_stability']);
        });
    }
};
