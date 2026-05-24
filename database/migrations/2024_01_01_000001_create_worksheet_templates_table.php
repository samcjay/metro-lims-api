<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('worksheet_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('instrument_type');
            $table->string('test_method_ref');
            $table->string('unit');
            $table->integer('max_test_points')->default(13);
            $table->integer('cycles')->default(2);
            $table->boolean('has_before_after')->default(false);
            $table->boolean('has_head_correction')->default(true);
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worksheet_templates');
    }
};
