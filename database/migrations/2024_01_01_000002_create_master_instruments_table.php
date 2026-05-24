<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('lab_id')->unique();           // e.g. MCS-LAB-PG-001
            $table->string('description');                // e.g. Working Standard Pressure Gauge
            $table->string('make');
            $table->string('model');
            $table->string('serial_no');
            $table->string('instrument_type');            // pressure_gauge, thermometer, etc.
            $table->string('range_from');
            $table->string('range_to');
            $table->string('unit');
            $table->decimal('accuracy_pct_fs', 8, 4);    // % FS
            $table->decimal('mpe', 12, 8);               // Maximum Permissible Error (absolute)
            $table->decimal('resolution_positive', 12, 8);
            $table->decimal('resolution_negative', 12, 8);
            $table->decimal('accountable_drift', 12, 8)->default(0);
            $table->json('poly_coefficients');            // {"a":..., "b":..., "c":..., "d":...}
            $table->json('poly_uncertainty_coefficients')->nullable();
            $table->decimal('max_correction_residual', 12, 8)->default(0);
            $table->decimal('max_uncertainty_residual', 12, 8)->default(0);
            $table->string('cal_certificate_no')->nullable();
            $table->string('cal_authority')->nullable();
            $table->date('last_calibrated_at')->nullable();
            $table->date('next_due_at')->nullable();
            $table->integer('cal_frequency_years')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_instruments');
    }
};
