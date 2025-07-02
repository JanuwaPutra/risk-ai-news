<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DocumentParserService;
use App\Services\AnalysisApiService;
use App\Services\ProgressService;
use App\Models\AnalysisResult;
use App\Models\Tokoh;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class AnalysisController extends Controller
{
    protected $documentParser;
    protected $analysisApi;
    protected $progress;
    
    /**
     * Create a new controller instance.
     *
     * @param DocumentParserService $documentParser
     * @param AnalysisApiService $analysisApi
     * @param ProgressService $progress
     */
    public function __construct(
        DocumentParserService $documentParser,
        AnalysisApiService $analysisApi,
        ProgressService $progress
    ) {
        $this->documentParser = $documentParser;
        $this->analysisApi = $analysisApi;
        $this->progress = $progress;
    }

    /**
     * Show the dashboard with overall statistics
     *
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        // Get statistics
        $stats = $this->getDefaultStats();
        
        // Get the latest results
        $results = AnalysisResult::orderBy('tanggal_tambah', 'desc')->get();
        
        // Calculate statistics if we have results
        if ($results->count() > 0) {
            $stats = $this->calculateStats($results);
        }
        
        // Count tokoh data
        $tokoh_count = Tokoh::count();
        
        return view('dashboard', [
            'results' => $results,
            'stats' => $stats,
            'tokoh_count' => $tokoh_count
        ]);
    }
    
    /**
     * Show the analysis page with filtering options
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function analysisPage(Request $request)
    {
        // Default filter values
        $kategoriFilter = $request->input('kategori', 'all');
        $urgensiFilter = $request->input('urgensi', 'all');
        
        // Query builder
        $query = AnalysisResult::query();
        
        // Apply filters
        if ($kategoriFilter !== 'all') {
            $query->where('kategori', $kategoriFilter);
        }
        
        if ($urgensiFilter !== 'all') {
            $query->where('urgensi', $urgensiFilter);
        }
        
        // Get filtered results
        $results = $query->orderBy('tanggal_tambah', 'desc')->get();
        
        // Get all results for stats (not filtered)
        $allResults = AnalysisResult::all();
        
        // Calculate statistics
        $stats = $this->calculateStats($allResults);
        
        return view('analysis', [
            'results' => $results,
            'selected_kategori' => $kategoriFilter,
            'selected_urgensi' => $urgensiFilter,
            'stats' => $stats
        ]);
    }

    /**
     * Display the upload and analysis page
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Default filter values
        $kategoriFilter = $request->input('kategori', 'all');
        
        // Initialize variables
        $beritaFilePath = null;
        $results = [];
        $stats = $this->getDefaultStats();
        
        // Check if form was submitted with files
        if ($request->isMethod('post') && $request->hasFile('berita_file')) {
            $beritaFile = $request->file('berita_file');
            
            if ($beritaFile) {
                // Store uploaded files
                $beritaFilePath = $beritaFile->store('uploads');
                
                // Convert storage path to actual file path
                $beritaFilePath = Storage::path($beritaFilePath);
                
                // Process uploaded file
                $results = $this->processFile($beritaFilePath);
                
                // Apply filter
                if ($kategoriFilter !== 'all') {
                    $results = $results->where('kategori', $kategoriFilter)->get();
                }
                
                // Calculate statistics
                $stats = $this->calculateStats($results);
            }
        } else {
            // When no files are uploaded, load existing results
            $query = AnalysisResult::query();
            
            // Apply filter
            if ($kategoriFilter !== 'all') {
                $query->where('kategori', $kategoriFilter);
            }
            
            $results = $query->orderBy('tanggal_tambah', 'desc')->get();
            
            // Calculate statistics if we have results
            if ($results->count() > 0) {
                $stats = $this->calculateStats($results);
            }
        }
        
        // Count tokoh data
        $tokohCount = Tokoh::count();
        
        // Generate current time
        $currentTime = now()->format('d F Y H:i');
        
        return view('index', [
            'results' => $results,
            'selected_kategori' => $kategoriFilter,
            'stats' => $stats,
            'current_time' => $currentTime,
            'tokoh_count' => $tokohCount
        ]);
    }
    
    /**
     * Process the uploaded berita file and perform analysis using tokoh from database
     *
     * @param string $beritaFilePath
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function processFile(string $beritaFilePath)
    {
        try {
            // Get all tokoh data from database
            $tokohData = Tokoh::all()->toArray();
            
            // Check if we have tokoh data
            if (empty($tokohData)) {
                Log::error("No tokoh data found in database. Please import tokoh data first.");
                return collect([]);
            }
            
            // Read paragraphs from uploaded document
            $paragraphs = $this->documentParser->readWordParagraphs($beritaFilePath);
            
            // Remove duplicate paragraphs
            $uniqueParagraphs = array_unique($paragraphs);
            
            // First count the total number of people to analyze
            $totalToAnalyze = 0;
            foreach ($uniqueParagraphs as $paragraph) {
                foreach ($tokohData as $person) {
                    if ($this->documentParser->matchPersonToParagraph($person, $paragraph)) {
                        $totalToAnalyze++;
                    }
                }
            }
            
            // Initialize progress tracking with the actual count of people to analyze
            $this->progress->initialize($totalToAnalyze);
            
            // Process each paragraph
            foreach ($uniqueParagraphs as $paragraph) {
                // Find matching people for this paragraph
                $matchedPeople = [];
                foreach ($tokohData as $person) {
                    if ($this->documentParser->matchPersonToParagraph($person, $paragraph)) {
                        $matchedPeople[] = $person;
                    }
                }
                
                // Process each matched person
                foreach ($matchedPeople as $person) {
                    try {
                        $personName = $person['nama'] ?? '';
                        if (empty($personName)) {
                            continue;
                        }
                        
                        $jabatan = $person['jabatan'] ?? 'N/A';
                        
                        // Update progress
                        $this->progress->increment(
                            "Analyzing for {$personName}",
                            substr($paragraph, 0, 50) . (strlen($paragraph) > 50 ? '...' : '')
                        );
                        
                        // Get personalized analysis
                        $analysis = $this->analysisApi->analyzeParagraph($paragraph, $personName, $jabatan);
                        
                        // Convert faktor_risiko array to string for storage
                        $faktorRisiko = $analysis['faktor_risiko'] ?? [];
                        if (is_array($faktorRisiko)) {
                            $faktorRisikoStr = implode(', ', $faktorRisiko);
                        } else {
                            $faktorRisikoStr = (string)$faktorRisiko;
                        }
                        
                        // Save result to database
                        AnalysisResult::updateOrCreate(
                            [
                                'nama' => $personName,
                                'paragraf' => $paragraph
                            ],
                            [
                                'jabatan' => $jabatan,
                                'ringkasan' => $analysis['ringkasan'] ?? 'N/A',
                                'skor_risiko' => $analysis['skor_risiko'] ?? 0,
                                'persentase_kerawanan' => $analysis['persentase_kerawanan'] ?? '0%',
                                'kategori' => $analysis['kategori'] ?? 'RENDAH',
                                'faktor_risiko' => $faktorRisikoStr,
                                'rekomendasi' => $analysis['rekomendasi'] ?? 'N/A',
                                'urgensi' => $analysis['urgensi'] ?? 'MONITORING',
                                'tanggal_tambah' => now()
                            ]
                        );
                        
                    } catch (\Exception $e) {
                        $personName = isset($person['nama']) ? $person['nama'] : 'Unknown';
                        Log::error("Error processing person {$personName}: {$e->getMessage()}");
                        continue;
                    }
                }
            }
            
            // Mark progress as complete
            $this->progress->complete();
            
            // Return all results from database
            return AnalysisResult::orderBy('tanggal_tambah', 'desc')->get();
            
        } catch (\Exception $e) {
            Log::error("Error processing file: {$e->getMessage()}");
            return collect([]);
        }
    }
    
    /**
     * Get default statistics structure
     *
     * @return array
     */
    private function getDefaultStats(): array
    {
        return [
            'total_berita' => 0,
            'total_tokoh' => 0,
            'kategori_count' => [
                'RENDAH' => 0,
                'SEDANG' => 0,
                'TINGGI' => 0,
                'KRITIS' => 0,
                'RENDAH_pct' => 0,
                'SEDANG_pct' => 0,
                'TINGGI_pct' => 0,
                'KRITIS_pct' => 0
            ],
            'urgensi_count' => [
                'MONITORING' => 0,
                'PERHATIAN' => 0,
                'SEGERA' => 0,
                'DARURAT' => 0
            ],
            'avg_skor' => 0
        ];
    }
    
    /**
     * Calculate statistics from results
     *
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @return array
     */
    private function calculateStats($results): array
    {
        $stats = $this->getDefaultStats();
        
        if ($results->isEmpty()) {
            return $stats;
        }
        
        // Count unique paragraphs and people
        $uniqueParagraphs = $results->pluck('paragraf')->unique();
        $uniquePeople = $results->pluck('nama')->unique();
        
        $stats['total_berita'] = $uniqueParagraphs->count();
        $stats['total_tokoh'] = $uniquePeople->count();
        
        // Count by kategori
        $stats['kategori_count']['RENDAH'] = $results->where('kategori', 'RENDAH')->count();
        $stats['kategori_count']['SEDANG'] = $results->where('kategori', 'SEDANG')->count();
        $stats['kategori_count']['TINGGI'] = $results->where('kategori', 'TINGGI')->count();
        $stats['kategori_count']['KRITIS'] = $results->where('kategori', 'KRITIS')->count();
        
        // Count by urgensi
        $stats['urgensi_count']['MONITORING'] = $results->where('urgensi', 'MONITORING')->count();
        $stats['urgensi_count']['PERHATIAN'] = $results->where('urgensi', 'PERHATIAN')->count();
        $stats['urgensi_count']['SEGERA'] = $results->where('urgensi', 'SEGERA')->count();
        $stats['urgensi_count']['DARURAT'] = $results->where('urgensi', 'DARURAT')->count();
        
        // Calculate average score
        $stats['avg_skor'] = $results->isEmpty() ? 0 : $results->avg('skor_risiko');
        
        // Calculate percentages for each category
        $total = array_sum(array_values($stats['kategori_count']));
        if ($total > 0) {
            foreach (['RENDAH', 'SEDANG', 'TINGGI', 'KRITIS'] as $key) {
                $stats['kategori_count']["{$key}_pct"] = round(($stats['kategori_count'][$key] / $total) * 100, 1);
            }
        }
        
        return $stats;
    }
    
    /**
     * Stream progress updates using Server-Sent Events
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function progress()
    {
        return response()->stream(function() {
            // Set headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable buffering for Nginx
            
            // Keep track of last state hash to avoid sending duplicates
            $lastStateHash = '';
            
            while (true) {
                // Get current progress state
                $state = $this->progress->getState();
                $currentHash = md5(json_encode($state));
                
                // Only send update if state has changed
                if ($currentHash !== $lastStateHash) {
                    echo "data: " . json_encode($state) . "\n\n";
                    $lastStateHash = $currentHash;
                } else {
                    // Send heartbeat for connection keep-alive
                    echo "data: " . json_encode(['heartbeat' => true]) . "\n\n";
                }
                
                // Flush output buffer
                ob_flush();
                flush();
                
                // Sleep to avoid CPU overuse (500ms)
                usleep(500000);
            }
        });
    }
    
    /**
     * API endpoint to check worker status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function workerStatus()
    {
        $state = $this->progress->getState();
        return response()->json($state);
    }
    
    /**
     * Export results to JSON file
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportResults()
    {
        try {
            // Get data from database
            $results = AnalysisResult::orderBy('tanggal_tambah', 'desc')->get();
            
            if ($results->isEmpty()) {
                return redirect()->route('dashboard')->with('warning', 'No results to export');
            }
            
            // Create a timestamp for the filename
            $timestamp = now()->format('Ymd_His');
            $filename = "results_export_{$timestamp}.json";
            
            // Convert collection to array
            $data = $results->toArray();
            
            // Prepare temporary file
            $tempFile = storage_path("app/temp_{$filename}");
            file_put_contents($tempFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Return the file for download
            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/json',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error("Error exporting results: {$e->getMessage()}");
            return redirect()->route('dashboard')->with('error', 'Error exporting results: ' . $e->getMessage());
        }
    }
}
