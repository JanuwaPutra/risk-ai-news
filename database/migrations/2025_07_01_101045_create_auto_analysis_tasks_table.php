<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auto_analysis_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->boolean('include_today')->default(true);
            $table->json('person_ids')->nullable();
            $table->enum('status', ['active', 'paused', 'completed', 'failed'])->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_article_date')->nullable();
            $table->integer('results_count')->default(0);
            $table->string('batch_id')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
        
        // Add task_id to analysis_results table
        Schema::table('analysis_results', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->nullable()->after('id');
            $table->index('task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_results', function (Blueprint $table) {
            $table->dropIndex(['task_id']);
            $table->dropColumn('task_id');
        });
        
        Schema::dropIfExists('auto_analysis_tasks');
    }
};
