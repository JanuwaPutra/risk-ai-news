<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProgressService
{
    private $cacheKey = 'analysis_progress';
    private $defaultState = [
        'total' => 0,
        'current' => 0,
        'status' => 'Idle',
        'message' => '',
        'last_analyzed' => '',
        'timestamp' => 0
    ];
    
    /**
     * Initialize the progress state
     *
     * @param int $total
     * @return void
     */
    public function initialize(int $total): void
    {
        $state = $this->defaultState;
        $state['total'] = $total;
        $state['current'] = 0;
        $state['status'] = 'Analyzing';
        $state['message'] = 'Starting analysis...';
        $state['timestamp'] = time();
        
        Cache::put($this->cacheKey, $state);
        
        Log::info("Progress initialized: {$total} items to process");
    }
    
    /**
     * Update the progress state
     *
     * @param array $data
     * @return void
     */
    public function update(array $data): void
    {
        $state = Cache::get($this->cacheKey, $this->defaultState);
        
        // Update specified fields
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $state)) {
                $state[$key] = $value;
            }
        }
        
        // Update timestamp
        $state['timestamp'] = time();
        
        // Save updated state
        Cache::put($this->cacheKey, $state);
        
        Log::info("Progress updated: {$state['current']}/{$state['total']} - {$state['message']}");
    }
    
    /**
     * Increment the progress counter
     *
     * @param string|null $message
     * @param string|null $lastAnalyzed
     * @return void
     */
    public function increment(?string $message = null, ?string $lastAnalyzed = null): void
    {
        $state = Cache::get($this->cacheKey, $this->defaultState);
        
        // Increment counter
        $state['current']++;
        
        // Update message if provided
        if ($message !== null) {
            $state['message'] = $message;
        }
        
        // Update last analyzed if provided
        if ($lastAnalyzed !== null) {
            $state['last_analyzed'] = $lastAnalyzed;
        }
        
        // Update timestamp
        $state['timestamp'] = time();
        
        // Save updated state
        Cache::put($this->cacheKey, $state);
        
        Log::info("Progress incremented: {$state['current']}/{$state['total']} - {$state['message']}");
    }
    
    /**
     * Complete the progress
     *
     * @return void
     */
    public function complete(): void
    {
        $state = Cache::get($this->cacheKey, $this->defaultState);
        
        // Mark as complete
        $state['status'] = 'Complete';
        $state['message'] = 'Analysis complete!';
        $state['timestamp'] = time();
        
        // Ensure current matches total
        $state['current'] = $state['total'];
        
        // Save updated state
        Cache::put($this->cacheKey, $state);
        
        Log::info("Progress completed: {$state['current']}/{$state['total']}");
    }
    
    /**
     * Get the current progress state
     *
     * @return array
     */
    public function getState(): array
    {
        return Cache::get($this->cacheKey, $this->defaultState);
    }
} 