<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('path');
            $table->string('output_path')->nullable();
            // pending | importing | classifying | exporting | done | failed
            $table->string('status')->default('pending');
            $table->unsignedInteger('header_row')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('batch_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('esg_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esg_file_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->char('text_hash', 40);
            $table->text('text');
            $table->string('category', 20)->nullable();
            $table->string('reason', 500)->nullable();

            $table->index(['esg_file_id', 'row_number']);
            $table->index(['esg_file_id', 'category']);
        });

        // Cache of results per report text, so duplicate reports (common) never hit the AI twice
        Schema::create('esg_cache', function (Blueprint $table) {
            $table->char('text_hash', 40)->primary();
            $table->string('category', 20);
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_rows');
        Schema::dropIfExists('esg_files');
        Schema::dropIfExists('esg_cache');
    }
};
