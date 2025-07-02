<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\AnalysisResult;
use Illuminate\Support\Facades\Log;

class FixController extends Controller
{
    /**
     * Check database schema for the analysis_results table
     */
    public function checkSchema()
    {
        $columns = Schema::getColumnListing('analysis_results');
        $hasUrlColumn = in_array('url', $columns);
        
        $message = "Checking database schema for analysis_results table.\n";
        $message .= "Available columns: " . implode(', ', $columns) . "\n";
        $message .= "Has 'url' column: " . ($hasUrlColumn ? "Yes" : "No") . "\n";
        
        // Check if there are any analysis results
        $count = DB::table('analysis_results')->count();
        $message .= "Total records in analysis_results: " . $count . "\n";
        
        return response($message, 200)->header('Content-Type', 'text/plain');
    }
    
    /**
     * Fix database issues
     */
    public function fixDatabaseIssues()
    {
        $message = "Starting database fix...\n";
        
        // Check if URL column exists
        if (!Schema::hasColumn('analysis_results', 'url')) {
            $message .= "URL column not found. Adding it now...\n";
            
            Schema::table('analysis_results', function ($table) {
                $table->string('url')->nullable()->after('source');
            });
            
            $message .= "URL column added successfully.\n";
        } else {
            $message .= "URL column already exists.\n";
        }
        
        // Check if any existing records need the URL field populated
        $count = DB::table('analysis_results')
            ->whereNull('url')
            ->count();
            
        $message .= "Found {$count} records with null URL values.\n";
        
        return response($message, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Fix urgency levels based on risk categories
     */
    public function fixUrgencyLevels()
    {
        $message = "Starting urgency levels fix...\n";
        
        try {
            // Get all analysis results
            $results = AnalysisResult::all();
            
            $count = $results->count();
            $message .= "Found {$count} analysis results to process\n";
            
            $updated = 0;
            
            foreach ($results as $result) {
                // Simpan saja, Model AnalysisResult akan otomatis menetapkan urgensi yang benar
                $result->save();
                $updated++;
            }
            
            $message .= "Successfully updated {$updated} urgency levels\n";
            
            return response($message, 200)->header('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            Log::error("Error fixing urgency levels: " . $e->getMessage());
            return response("Error: " . $e->getMessage(), 500)->header('Content-Type', 'text/plain');
        }
    }
    
    // Metode determineUrgencyFromCategory dihapus karena logika sudah dipindahkan ke model AnalysisResult
}
