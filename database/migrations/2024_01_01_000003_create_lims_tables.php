<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('worksheet_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_template_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->enum('type', ['number', 'text', 'select', 'computed']);
            $table->string('unit')->nullable();
            $table->integer('decimal_places')->default(4);
            $table->json('options')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('repeats_per_test_point')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('group')->nullable();
            $table->timestamps();
        });

        Schema::create('formula_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_template_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('symbol')->nullable();
            $table->enum('uncertainty_type', ['A', 'B', 'none'])->default('none');
            $table->text('formula');
            $table->json('depends_on')->nullable();
            $table->string('divisor_formula')->nullable();
            $table->decimal('sensitivity_coeff', 8, 4)->default(1);
            $table->boolean('is_output')->default(false);
            $table->boolean('scope_per_test_point')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cmc_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_template_id')->constrained()->cascadeOnDelete();
            $table->decimal('range_from', 12, 6);
            $table->decimal('range_to', 12, 6);
            $table->string('unit');
            $table->string('medium')->default('pneumatic'); // pneumatic, hydraulic_oil, hydraulic_water
            $table->decimal('cmc_value', 12, 8);
            $table->string('cmc_formula')->nullable();
            $table->timestamps();
        });

        Schema::create('calibration_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_no')->unique();
            $table->string('customer_name');
            $table->string('customer_ref')->nullable();
            $table->date('date_received');
            $table->date('date_calibrated')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('calibration_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calibration_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worksheet_template_id')->constrained();
            $table->foreignId('master_instrument_id')->constrained();
            $table->string('uuc_description')->nullable();
            $table->string('uuc_make_model')->nullable();
            $table->string('uuc_serial_no')->nullable();
            $table->string('uuc_instrument_id')->nullable();
            $table->string('uuc_range')->nullable();
            $table->string('uuc_unit')->nullable();
            $table->string('status')->default('draft');
            $table->json('field_values')->nullable();
            $table->json('result_snapshot')->nullable();
            $table->string('engine_version')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users');
            $table->string('metrologist_signature')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users');
            $table->string('authorizer_signature')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->foreignId('authorized_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('result_data_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calibration_result_id')->constrained()->cascadeOnDelete();
            $table->integer('test_point_index');
            $table->decimal('nominal_value', 12, 6);
            $table->decimal('uuc_mean', 12, 6)->nullable();
            $table->decimal('ref_mean', 12, 6)->nullable();
            $table->decimal('error_of_indication', 12, 6)->nullable();
            $table->decimal('hysteresis', 12, 6)->nullable();
            $table->decimal('repeatability', 12, 6)->nullable();
            $table->decimal('u_combined', 12, 8)->nullable();
            $table->decimal('u_expanded', 12, 8)->nullable();
            $table->decimal('cmc_value', 12, 8)->nullable();
            $table->decimal('u_final', 12, 8)->nullable();
            $table->string('u_source')->nullable();
            $table->boolean('within_mpe')->nullable();
            $table->json('budget_components')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_data_points');
        Schema::dropIfExists('calibration_results');
        Schema::dropIfExists('calibration_jobs');
        Schema::dropIfExists('cmc_entries');
        Schema::dropIfExists('formula_nodes');
        Schema::dropIfExists('worksheet_fields');
    }
};
