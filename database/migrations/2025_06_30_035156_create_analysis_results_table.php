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
        Schema::create('analysis_results', function (Blueprint $table) {
            $table->id();
            $table->string('nama'); // Person name
            $table->string('jabatan')->nullable(); // Person position/role
            $table->text('paragraf'); // News paragraph
            $table->text('ringkasan')->nullable(); // Summary
            $table->integer('skor_risiko')->default(0); // Risk score (0-100)
            $table->string('persentase_kerawanan')->default('0%'); // Risk percentage
            $table->string('kategori')->default('RENDAH'); // Risk category (RENDAH, SEDANG, TINGGI, KRITIS)
            $table->text('faktor_risiko')->nullable(); // Risk factors (stored as JSON)
            $table->text('rekomendasi')->nullable(); // Recommendation
            $table->string('urgensi')->default('MONITORING'); // Urgency level (MONITORING, PERHATIAN, SEGERA, DARURAT)
            $table->timestamp('tanggal_tambah')->useCurrent(); // Date added
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
