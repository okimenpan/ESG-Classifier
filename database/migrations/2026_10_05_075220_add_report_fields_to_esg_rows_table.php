<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report columns shown on the dashboard (taken from the original Excel row).
     */
    public function up(): void
    {
        Schema::table('esg_rows', function (Blueprint $table) {
            $table->string('tracking_id', 50)->nullable();
            $table->date('report_date')->nullable();
            $table->string('title', 500)->nullable();
            $table->text('content')->nullable();
            $table->string('agency')->nullable();
            $table->string('agency_unit')->nullable();
            $table->string('report_status', 100)->nullable();

            $table->index('report_date');
            $table->index(['report_status', 'category']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('esg_rows', function (Blueprint $table) {
            $table->dropIndex(['report_date']);
            $table->dropIndex(['report_status', 'category']);
            $table->dropIndex(['category']);
            $table->dropColumn(['tracking_id', 'report_date', 'title', 'content', 'agency', 'agency_unit', 'report_status']);
        });
    }
};
