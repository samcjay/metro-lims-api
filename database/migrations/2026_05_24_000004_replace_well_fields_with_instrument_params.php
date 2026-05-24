<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('master_instruments', function (Blueprint $table) {
            $table->json('instrument_params')->nullable()->after('accountable_drift');
        });

        // Migrate existing well data into instrument_params
        DB::table('master_instruments')
            ->orderBy("id")->each(function ($row) {
                $params = [];
                if ($row->well_axial    !== null) $params['well_axial']    = $row->well_axial;
                if ($row->well_radial   !== null) $params['well_radial']   = $row->well_radial;
                if ($row->well_stability!== null) $params['well_stability']= $row->well_stability;
                if ($params) {
                    DB::table('master_instruments')
                        ->where('id', $row->id)
                        ->update(['instrument_params' => json_encode($params)]);
                }
            });

        Schema::table('master_instruments', function (Blueprint $table) {
            $table->dropColumn(['well_axial', 'well_radial', 'well_stability']);
        });
    }

    public function down(): void {
        Schema::table('master_instruments', function (Blueprint $table) {
            $table->decimal('well_axial',    8, 4)->nullable();
            $table->decimal('well_radial',   8, 4)->nullable();
            $table->decimal('well_stability', 8, 4)->nullable();
        });
        Schema::table('master_instruments', function (Blueprint $table) {
            $table->dropColumn('instrument_params');
        });
    }
};
