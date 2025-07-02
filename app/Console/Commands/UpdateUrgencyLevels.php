<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AnalysisResult;
use Illuminate\Support\Facades\Log;

class UpdateUrgencyLevels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-urgency-levels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update urgency levels based on risk categories';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to update urgency levels...');
        
        try {
            // Get all analysis results, not just empty ones
            $results = AnalysisResult::all();
            
            $count = $results->count();
            $this->info("Found {$count} results to process");
            
            $updated = 0;
            
            foreach ($results as $result) {
                // Simpan saja, model AnalysisResult akan mengatur urgency secara otomatis
                $result->save();
                $updated++;
                
                if ($updated % 50 == 0) {
                    $this->info("Updated {$updated} of {$count} results");
                }
            }
            
            $this->info("Successfully updated {$updated} urgency levels");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error updating urgency levels: " . $e->getMessage());
            Log::error("Error in UpdateUrgencyLevels command: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    // Metode determineUrgencyFromCategory dihapus karena logika sudah dipindahkan ke model AnalysisResult
}
