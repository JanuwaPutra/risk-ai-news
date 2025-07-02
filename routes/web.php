<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\TokohController;
use App\Http\Controllers\NewsController;

// Dashboard as home
Route::get('/', [AnalysisController::class, 'dashboard'])->name('dashboard');

// Analysis pages
Route::get('/analysis', [AnalysisController::class, 'analysisPage'])->name('analysis');
Route::post('/analysis', [AnalysisController::class, 'analysisPage']);

// Upload and analysis process
Route::get('/upload', [AnalysisController::class, 'index'])->name('index');
Route::post('/upload', [AnalysisController::class, 'index']);

// Progress and worker routes
Route::get('/progress', [AnalysisController::class, 'progress'])->name('progress');
Route::get('/worker-status', [AnalysisController::class, 'workerStatus'])->name('worker.status');

// Tokoh management routes
Route::get('/tokoh', [TokohController::class, 'index'])->name('tokoh.index');
Route::get('/tokoh/import', [TokohController::class, 'importForm'])->name('tokoh.import.form');
Route::post('/tokoh/import', [TokohController::class, 'import'])->name('tokoh.import');
Route::delete('/tokoh/delete-all', [TokohController::class, 'deleteAll'])->name('tokoh.delete.all');
Route::post('/tokoh/update-field', [TokohController::class, 'updateField'])->name('tokoh.update.field');

// News search routes
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/fetch-full', [NewsController::class, 'fetchFull'])->name('news.fetch-full');
Route::get('/news/analyze', [NewsController::class, 'index'])->name('news.analyze.get');
Route::get('/news/analyze-all', [NewsController::class, 'index'])->name('news.analyze-all.get');
Route::post('/news/analyze', [NewsController::class, 'analyzeArticle'])->name('news.analyze');
Route::post('/news/analyze-all', [NewsController::class, 'analyzeArticleForAll'])->name('news.analyze-all');
