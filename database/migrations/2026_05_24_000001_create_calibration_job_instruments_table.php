<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_job_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calibration_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_instrument_id')->constrained();
            $table->string('role', 50); // e.g. reference, dry_well, secondary_reference
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['calibration_result_id', 'master_instrument_id', 'role'], 'cji_result_instrument_role_unique');
        });

        // Drop the old single-instrument FK from calibration_results (make nullable first)
        Schema::table('calibration_results', function (Blueprint $table) {
            $table->foreignId('master_instrument_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_job_instruments');
        Schema::table('calibration_results', function (Blueprint $table) {
            $table->foreignId('master_instrument_id')->nullable(false)->change();
        });
    }
};
