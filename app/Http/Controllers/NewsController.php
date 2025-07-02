<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Services\AnalysisApiService;
use App\Models\AnalysisResult;
use App\Models\Tokoh;
use Carbon\Carbon;
use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;

class NewsController extends Controller
{
    protected $analysisApi;
    
    /**
     * Create a new controller instance.
     *
     * @param AnalysisApiService $analysisApi
     */
    public function __construct(AnalysisApiService $analysisApi)
    {
        $this->analysisApi = $analysisApi;
    }
    
    /**
     * Show the news search page
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $query = $request->input('query');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $includeToday = $request->boolean('include_today');
        $articles = [];
        
        if ($query) {
            $articles = $this->searchNews($query, $fromDate, $toDate, $includeToday);
        }
        
        // Get tokoh data for analysis
        $tokohData = Tokoh::all();
        
        return view('news.index', [
            'query' => $query,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'includeToday' => $includeToday,
            'articles' => $articles,
            'tokohData' => $tokohData
        ]);
    }
    
    /**
     * Search news from NewsAPI with advanced date filtering
     *
     * @param string $query
     * @param string|null $fromDate
     * @param string|null $toDate
     * @param bool $includeToday
     * @return array
     */
    private function searchNews(string $query, ?string $fromDate = null, ?string $toDate = null, bool $includeToday = false): array
    {
        try {
            $params = [
                'q' => $query,
                'language' => 'id',
                'sortBy' => 'publishedAt',
                'apiKey' => env('NEWSAPI_KEY', 'f515168ded7a4f2f98ca875cf65b3316')
            ];
            
            // Set default from date if not provided
            if (!$fromDate) {
                $fromDate = now()->subDays(3)->format('Y-m-d'); // Default to 3 days ago
            }
            
            // Add from date
            $params['from'] = $fromDate;
            
            // Add to date if provided
            if ($toDate) {
                $params['to'] = $toDate;
            }
            
            // Special case for today
            if ($includeToday) {
                // If we have a to date, remove it and set to today
                $params['to'] = now()->format('Y-m-d');
            }
            
            $response = Http::get(env('NEWSAPI_URL', 'https://newsapi.org/v2/everything'), $params);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'ok' && isset($data['articles'])) {
                    return $data['articles'];
                }
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error("Error fetching news: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetch full article content
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchFull(Request $request)
    {
        $url = $request->input('url');
        
        if (!$url) {
            return response()->json(['error' => 'URL is required'], 400);
        }
        
        try {
            $html = Http::get($url)->body();
            
            $configuration = new Configuration();
            $configuration->setFixRelativeURLs(true);
            $configuration->setOriginalURL($url);
            
            $readability = new Readability($configuration, $html);
            $result = $readability->parse($html);
            
            if (!is_array($result) || !isset($result['content'])) {
                return response()->json(['error' => 'Failed to parse article content'], 500);
            }
            
            $content = $result['content'];
            $plainText = strip_tags($content);
            
            return response()->json([
                'title' => $readability->getTitle(),
                'content' => $readability->getContent(),
                'excerpt' => $readability->getExcerpt(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching full article: " . $e->getMessage());
            return response()->json(['error' => 'Failed to parse content: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Analyze news article for a specific person
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzeArticle(Request $request)
    {
        $url = $request->input('url');
        $title = $request->input('title');
        $source = $request->input('source');
        $searchQuery = $request->input('search_query', '');
        
        try {
            // Validate person ID if provided
            $personId = $request->input('person_id');
            if (!$personId) {
                return response()->json(['error' => 'Person ID is required'], 400);
            }
            
            $person = Tokoh::find($personId);
            if (!$person) {
                return response()->json(['error' => 'Person not found'], 404);
            }
            
            // Fetch full article content
            try {
                $response = Http::timeout(10)->get($url);
                
                if (!$response->successful()) {
                    Log::error("Failed to fetch article from URL: {$url}, status code: {$response->status()}");
                    return response()->json(['error' => "Failed to fetch article: HTTP {$response->status()}"], 500);
                }
                
                $html = $response->body();
                
                if (empty($html)) {
                    Log::error("Empty response from URL: {$url}");
                    return response()->json(['error' => 'Empty response from URL'], 500);
                }
            } catch (\Exception $e) {
                Log::error("Exception fetching URL {$url}: " . $e->getMessage());
                return response()->json(['error' => "Failed to fetch article: {$e->getMessage()}"], 500);
            }
            
            // Try to parse with Readability
            try {
                $configuration = new Configuration();
                $readability = new Readability($configuration);
                $result = $readability->parse($html);
                
                // Check if we have valid content
                if (!$result || !is_array($result) || !isset($result['content'])) {
                    Log::warning("Readability failed to parse article content from URL: {$url}, using fallback method");
                    
                    // Fallback: use simple content extraction
                    $content = $this->extractContentFallback($html);
                    
                    if (empty($content)) {
                        Log::error("All content extraction methods failed for URL: {$url}");
                        return response()->json(['error' => 'Failed to parse article content. This may be due to the website structure or anti-scraping measures.'], 500);
                    }
                } else {
                    $content = $result['content'];
                }
            } catch (\Exception $e) {
                Log::error("Readability parsing error for URL {$url}: " . $e->getMessage());
                
                // Try fallback method
                $content = $this->extractContentFallback($html);
                
                if (empty($content)) {
                    Log::error("All content extraction methods failed for URL: {$url}");
                    return response()->json(['error' => "Failed to parse article: {$e->getMessage()}"], 500);
                }
                
                Log::info("Used fallback extraction method for URL: {$url}");
            }
            
            $plainText = strip_tags($content);
            
            if (empty($plainText)) {
                Log::error("Empty plain text after parsing URL: {$url}");
                return response()->json(['error' => 'No readable content found in article'], 500);
            }
            
            // Check if the person's name is actually mentioned in the content with STRICT boundary checking
            $nameFound = false;
            $aliasFound = false;
            $exactMatchPattern = '/\b' . preg_quote($person->nama, '/') . '\b/i';
            $nameFound = preg_match($exactMatchPattern, $plainText, $nameMatches);
            
            if ($nameFound) {
                Log::info("Found name '{$person->nama}' in article with exact match: " . $nameMatches[0]);
            }
            
            // Only check aliases if name not found
            if (!$nameFound && !empty($person->alias)) {
                $aliases = preg_split('/[,;\n]+/', $person->alias);
                foreach ($aliases as $alias) {
                    $alias = trim($alias);
                    if (!empty($alias)) {
                        $aliasPattern = '/\b' . preg_quote($alias, '/') . '\b/i';
                        if (preg_match($aliasPattern, $plainText, $aliasMatches)) {
                            $aliasFound = true;
                            Log::info("Found alias '{$alias}' for person '{$person->nama}' in article with match: " . $aliasMatches[0]);
                            break;
                        }
                    }
                }
            }
            
            // Debug log the exact state
            Log::info("Single article analysis for '{$person->nama}': nameFound={$nameFound}, aliasFound={$aliasFound}");
            
            if (!$nameFound && !$aliasFound) {
                return response()->json([
                    'error' => "Tidak ada pernyataan atau tindakan dari tokoh '{$person->nama}' yang dianalisis karena tidak ditemukan dalam berita."
                ], 400);
            }
            
            // Perform analysis
            $analysis = $this->analysisApi->analyzeParagraph(
                $plainText, 
                $person->nama, 
                $person->jabatan ?? 'Tidak ada jabatan' // Provide default value if jabatan is null
            );
            
            // Check if name was actually found (double check with API service)
            if (isset($analysis['name_not_found']) && $analysis['name_not_found'] === true) {
                return response()->json([
                    'error' => "Tokoh '{$person->nama}' tidak memiliki pernyataan atau tindakan dalam berita ini karena nama tokoh tidak ditemukan dalam konten berita."
                ], 400);
            }
            
            // Convert faktor_risiko array to string for storage
            $faktorRisiko = $analysis['faktor_risiko'] ?? [];
            if (is_array($faktorRisiko)) {
                $faktorRisikoStr = implode(', ', $faktorRisiko);
            } else {
                $faktorRisikoStr = (string)$faktorRisiko;
            }
            
            // Save result to database
            $analysisResult = AnalysisResult::updateOrCreate(
                [
                    'nama' => $person->nama,
                    'paragraf' => substr($plainText, 0, 1000), // Store first 1000 chars
                ],
                [
                    'jabatan' => $person->jabatan,
                    'source' => $source,
                    'kata_kunci' => $searchQuery,
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
            
            return response()->json([
                'success' => true,
                'analysis' => $analysis,
                'result_id' => $analysisResult->id
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error analyzing article: " . $e->getMessage());
            return response()->json(['error' => 'Failed to analyze article: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Analyze news article for all people in the database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzeArticleForAll(Request $request)
    {
        $url = $request->input('url');
        $title = $request->input('title');
        $source = $request->input('source');
        $searchQuery = $request->input('search_query', '');
        
        if (!$url) {
            return response()->json(['error' => 'URL is required'], 400);
        }
        
        try {
            // Fetch all people from database
            $people = Tokoh::all();
            
            if ($people->isEmpty()) {
                return response()->json(['error' => 'No people found in the database'], 400);
            }
            
            // Fetch full article content once
            try {
                $response = Http::timeout(10)->get($url);
                
                if (!$response->successful()) {
                    Log::error("Failed to fetch article from URL: {$url}, status code: {$response->status()}");
                    return response()->json(['error' => "Failed to fetch article: HTTP {$response->status()}"], 500);
                }
                
                $html = $response->body();
                
                if (empty($html)) {
                    Log::error("Empty response from URL: {$url}");
                    return response()->json(['error' => 'Empty response from URL'], 500);
                }
            } catch (\Exception $e) {
                Log::error("Exception fetching URL {$url}: " . $e->getMessage());
                return response()->json(['error' => "Failed to fetch article: {$e->getMessage()}"], 500);
            }
            
            // Try to parse with Readability
            try {
                $configuration = new Configuration();
                
                $readability = new Readability($configuration);
                $result = $readability->parse($html);
                
                // Check if we have valid content
                if (!$result || !is_array($result) || !isset($result['content'])) {
                    Log::warning("Readability failed to parse article content from URL: {$url}, using fallback method");
                    
                    // Fallback: use simple content extraction
                    $content = $this->extractContentFallback($html);
                    
                    if (empty($content)) {
                        Log::error("All content extraction methods failed for URL: {$url}");
                        return response()->json(['error' => 'Failed to parse article content. This may be due to the website structure or anti-scraping measures.'], 500);
                    }
                } else {
                    $content = $result['content'];
                }
            } catch (\Exception $e) {
                Log::error("Readability parsing error for URL {$url}: " . $e->getMessage());
                
                // Try fallback method
                $content = $this->extractContentFallback($html);
                
                if (empty($content)) {
                    Log::error("All content extraction methods failed for URL: {$url}");
                    return response()->json(['error' => "Failed to parse article: {$e->getMessage()}"], 500);
                }
                
                Log::info("Used fallback extraction method for URL: {$url}");
            }
            
            // Extract plain text
            $plainText = strip_tags($content);
            
            if (empty($plainText)) {
                Log::error("Empty plain text after parsing URL: {$url}");
                return response()->json(['error' => 'No readable content found in article'], 500);
            }
            
            $results = [];
            $resultCount = 0;
            
            // Analyze for each person
            foreach ($people as $person) {
                try {
                    // Check if the person's name is mentioned in the article with STRICT boundary matching
                    $nameFound = false;
                    $aliasFound = false;
                    $exactMatchPattern = '/\b' . preg_quote($person->nama, '/') . '\b/i';
                    $nameFound = preg_match($exactMatchPattern, $plainText);
                    
                    // Only check aliases if name not found
                    if (!$nameFound && !empty($person->alias)) {
                        // Split aliases by comma, semicolon, or newline
                        $aliases = preg_split('/[,;\n]+/', $person->alias);
                        foreach ($aliases as $alias) {
                            $alias = trim($alias);
                            if (!empty($alias)) {
                                $aliasPattern = '/\b' . preg_quote($alias, '/') . '\b/i';
                                if (preg_match($aliasPattern, $plainText)) {
                                    $aliasFound = true;
                                    Log::info("Found alias '{$alias}' for person '{$person->nama}' in article");
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Debug log the exact state
                    Log::info("Person '{$person->nama}': nameFound={$nameFound}, aliasFound={$aliasFound}");
                    
                    // If either name or alias is found with STRICT boundary matching, proceed with analysis
                    if ($nameFound || $aliasFound) {
                        Log::info("PROCEEDING with analysis for '{$person->nama}' - strict name/alias check PASSED");
                        
                        // Perform analysis for this person
                        $analysis = $this->analysisApi->analyzeParagraph(
                            $plainText, 
                            $person->nama, 
                            $person->jabatan ?? 'Tidak ada jabatan' // Provide default value if jabatan is null
                        );
                        
                        // Check if name was actually found (double check with API service)
                        if (isset($analysis['name_not_found']) && $analysis['name_not_found'] === true) {
                            Log::info("API confirmed {$person->nama} not found in article, skipping database entry");
                            continue; // Skip to next person without adding to database
                        }
                        
                        // More validation - look for entire name with clear word boundaries
                        // This is a super strict check to catch any edge cases
                        $foundInContext = false;
                        $namePatternStrict = '/\b' . preg_quote($person->nama, '/') . '\b/i';
                        
                        if (preg_match($namePatternStrict, $plainText, $matches)) {
                            $foundInContext = true;
                            Log::info("Super strict check found name '{$person->nama}' in article with match: " . $matches[0]);
                        } else if (!empty($person->alias)) {
                            // Check aliases with super strict boundaries
                            $aliases = preg_split('/[,;\n]+/', $person->alias);
                            foreach ($aliases as $alias) {
                                $alias = trim($alias);
                                if (!empty($alias)) {
                                    $aliasPatternStrict = '/\b' . preg_quote($alias, '/') . '\b/i';
                                    if (preg_match($aliasPatternStrict, $plainText, $matches)) {
                                        $foundInContext = true;
                                        Log::info("Super strict check found alias '{$alias}' in article with match: " . $matches[0]);
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Skip database entry if super strict check fails
                        if (!$foundInContext) {
                            Log::info("Skipping database entry for {$person->nama} - super strict name/alias check failed");
                            continue;
                        }
                        
                        // Now we're CERTAIN the name is in the article - proceed with database entry
                        Log::info("CONFIRMED: {$person->nama} is definitely in the article, proceeding with database entry");
                        
                        // Convert faktor_risiko array to string for storage
                        $faktorRisiko = $analysis['faktor_risiko'] ?? [];
                        if (is_array($faktorRisiko)) {
                            $faktorRisikoStr = implode(', ', $faktorRisiko);
                        } else {
                            $faktorRisikoStr = (string)$faktorRisiko;
                        }
                        
                        // Save result to database
                        $analysisResult = AnalysisResult::updateOrCreate(
                            [
                                'nama' => $person->nama,
                                'paragraf' => substr($plainText, 0, 1000), // Store first 1000 chars
                            ],
                            [
                                'jabatan' => $person->jabatan,
                                'source' => $source,
                                'kata_kunci' => $searchQuery,
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
                        
                        $results[] = [
                            'name' => $person->nama,
                            'category' => $analysis['kategori'] ?? 'RENDAH',
                            'score' => $analysis['skor_risiko'] ?? 0
                        ];
                        
                        $resultCount++;
                    } else {
                        // Skip this person - name not found in article
                        Log::info("Skipped analysis for {$person->nama}: Name not found in article");
                    }
                } catch (\Exception $e) {
                    Log::error("Error analyzing article for person {$person->nama}: " . $e->getMessage());
                    // Continue with next person
                }
            }
            
            // Always return success, even if no people were found
            return response()->json([
                'success' => true,
                'analyzed_count' => $resultCount,
                'results' => $results,
                'no_people_found' => ($resultCount === 0)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error analyzing article for all people: " . $e->getMessage());
            return response()->json(['error' => 'Failed to analyze article: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Show the automatic news analysis page
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function autoAnalysis(Request $request)
    {
        // Get tokoh data for analysis
        $tokohData = Tokoh::all();
        
        // Get old values from session if available
        $query = $request->session()->get('auto_search.query', '');
        $fromDate = $request->session()->get('auto_search.from_date', now()->subDays(3)->format('Y-m-d'));
        $toDate = $request->session()->get('auto_search.to_date', '');
        $includeToday = $request->session()->get('auto_search.include_today', true);
        $personIds = $request->session()->get('auto_search.person_ids', []);
        
        return view('news.auto', [
            'tokohData' => $tokohData,
            'query' => $query,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'includeToday' => $includeToday,
            'personIds' => $personIds
        ]);
    }
    
    /**
     * Process automatic news analysis
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processAutoAnalysis(Request $request)
    {
        $query = $request->input('query');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $includeToday = $request->boolean('include_today');
        $personIds = $request->input('person_ids', []);
        
        // Store search parameters in session
        $request->session()->put('auto_search.query', $query);
        $request->session()->put('auto_search.from_date', $fromDate);
        $request->session()->put('auto_search.to_date', $toDate);
        $request->session()->put('auto_search.include_today', $includeToday);
        $request->session()->put('auto_search.person_ids', $personIds);
        
        if (!$query) {
            return response()->json(['error' => 'Kata kunci pencarian diperlukan'], 400);
        }
        
        try {
            // Create a persistent task for continuous background processing
            $task = \App\Models\AutoAnalysisTask::create([
                'query' => $query,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'include_today' => $includeToday,
                'person_ids' => $personIds,
                'status' => \App\Models\AutoAnalysisTask::STATUS_ACTIVE,
            ]);
            
            // Start the initial job
            \App\Jobs\ProcessAutoNewsAnalysis::dispatch($task);
            
            // Get the news articles for immediate display
            $articles = $this->searchNews($query, $fromDate, $toDate, $includeToday);
            
            if (empty($articles)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tidak ada berita yang ditemukan dengan kata kunci tersebut, tetapi analisis otomatis akan terus berjalan di latar belakang dan memeriksa berita baru',
                    'articles' => [],
                    'results' => [],
                    'totalProcessed' => 0,
                    'task_id' => $task->id,
                    'background_running' => true
                ]);
            }
            
            // Process first batch in this request for immediate feedback
            $processedArticles = [];
            $analysisResults = [];
            
            // Only process the first 2-3 articles for immediate feedback
            $articlesToProcess = array_slice($articles, 0, 3);
            
            foreach ($articlesToProcess as $article) {
                try {
                    $url = $article['url'];
                    $title = $article['title'];
                    $source = $article['source']['name'] ?? 'Unknown';
                    
                    // Fetch full article content
                    $html = Http::get($url)->body();
                    
                    $configuration = new Configuration();
                    $readability = new Readability($configuration);
                    $result = $readability->parse($html);
                    
                    // Check if we have valid content
                    if (!is_array($result) || !isset($result['content'])) {
                        return response()->json(['error' => 'Failed to parse article content'], 500);
                    }
                    
                    $content = $result['content'];
                    $plainText = strip_tags($content);
                    
                    // Create a processed article entry
                    $processedArticle = [
                        'title' => $title,
                        'url' => $url,
                        'source' => $source,
                        'publishedAt' => $article['publishedAt'],
                        'results' => []
                    ];
                    
                    // Get people to analyze
                    $people = empty($personIds) 
                        ? Tokoh::all() 
                        : Tokoh::whereIn('id', $personIds)->get();
                    
                    if ($people->isEmpty()) {
                        continue;
                    }
                    
                    // Analyze for each person
                    foreach ($people as $person) {
                        // Check if the person's name is mentioned in the article
                        $nameFound = false;
                        $aliasFound = false;
                        $exactMatchPattern = '/\b' . preg_quote($person->nama, '/') . '\b/i';
                        $nameFound = preg_match($exactMatchPattern, $plainText);
                        
                        // Check aliases if name not found
                        if (!$nameFound && !empty($person->alias)) {
                            $aliases = preg_split('/[,;\n]+/', $person->alias);
                            foreach ($aliases as $alias) {
                                $alias = trim($alias);
                                if (!empty($alias)) {
                                    $aliasPattern = '/\b' . preg_quote($alias, '/') . '\b/i';
                                    if (preg_match($aliasPattern, $plainText)) {
                                        $aliasFound = true;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // If either name or alias is found, proceed with analysis
                        if ($nameFound || $aliasFound) {
                            // Perform analysis for this person
                            $analysis = $this->analysisApi->analyzeParagraph(
                                $plainText, 
                                $person->nama, 
                                $person->jabatan ?? 'Tidak ada jabatan'
                            );
                            
                            // Skip if name not found in analysis
                            if (isset($analysis['name_not_found']) && $analysis['name_not_found'] === true) {
                                continue;
                            }
                            
                            // Convert faktor_risiko array to string for storage
                            $faktorRisiko = $analysis['faktor_risiko'] ?? [];
                            if (is_array($faktorRisiko)) {
                                $faktorRisikoStr = implode(', ', $faktorRisiko);
                            } else {
                                $faktorRisikoStr = (string)$faktorRisiko;
                            }
                            
                            // Save result to database
                            $analysisResult = AnalysisResult::updateOrCreate(
                                [
                                    'nama' => $person->nama,
                                    'paragraf' => substr($plainText, 0, 1000), // Store first 1000 chars
                                    'task_id' => $task->id,
                                ],
                                [
                                    'jabatan' => $person->jabatan,
                                    'source' => $source,
                                    'kata_kunci' => $query,
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
                            
                            // Add to results
                            $processedArticle['results'][] = [
                                'name' => $person->nama,
                                'category' => $analysis['kategori'] ?? 'RENDAH',
                                'score' => $analysis['skor_risiko'] ?? 0,
                                'urgency' => $analysis['urgensi'] ?? 'MONITORING'
                            ];
                            
                            $analysisResults[] = [
                                'id' => $analysisResult->id,
                                'nama' => $person->nama,
                                'jabatan' => $person->jabatan,
                                'skor_risiko' => $analysis['skor_risiko'] ?? 0,
                                'kategori' => $analysis['kategori'] ?? 'RENDAH',
                                'source' => $source,
                                'title' => $title
                            ];
                        }
                    }
                    
                    // Only add articles that had at least one person analyzed
                    if (!empty($processedArticle['results'])) {
                        $processedArticles[] = $processedArticle;
                    }
                    
                } catch (\Exception $e) {
                    Log::error("Error processing article: " . $e->getMessage());
                    // Continue with next article
                }
            }
            
            // Update task results count
            $task->results_count = count($analysisResults);
            $task->save();
            
            return response()->json([
                'success' => true,
                'articles' => $processedArticles,
                'results' => $analysisResults,
                'totalProcessed' => count($processedArticles),
                'message' => count($processedArticles) > 0 
                    ? 'Analisis berita otomatis berhasil dan akan terus berjalan di latar belakang untuk berita baru' 
                    : 'Tidak ada tokoh yang ditemukan dalam berita, tetapi analisis akan terus berjalan di latar belakang',
                'task_id' => $task->id,
                'background_running' => true,
                'total_articles' => count($articles),
                'processed_immediately' => count($articlesToProcess)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error in auto analysis: " . $e->getMessage());
            return response()->json(['error' => 'Gagal melakukan analisis otomatis: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Check status of a background analysis task
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkTaskStatus(Request $request)
    {
        $taskId = $request->input('task_id');
        
        if (!$taskId) {
            return response()->json(['error' => 'ID tugas diperlukan'], 400);
        }
        
        try {
            $task = \App\Models\AutoAnalysisTask::findOrFail($taskId);
            
            // Get the latest results
            $results = AnalysisResult::where('task_id', $taskId)
                ->orderBy('tanggal_tambah', 'desc')
                ->take(20)
                ->get();
                
            $formattedResults = [];
            
            foreach ($results as $result) {
                $formattedResults[] = [
                    'id' => $result->id,
                    'nama' => $result->nama,
                    'jabatan' => $result->jabatan,
                    'skor_risiko' => $result->skor_risiko,
                    'kategori' => $result->kategori,
                    'source' => $result->source,
                    'title' => substr($result->paragraf, 0, 100) . '...'
                ];
            }
            
            return response()->json([
                'success' => true,
                'task' => [
                    'id' => $task->id,
                    'query' => $task->query,
                    'status' => $task->status,
                    'results_count' => $task->results_count,
                    'last_run_at' => $task->last_run_at ? $task->last_run_at->diffForHumans() : null,
                    'created_at' => $task->created_at->diffForHumans(),
                ],
                'results' => $formattedResults,
                'is_active' => $task->isActive()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error checking task status: " . $e->getMessage());
            return response()->json(['error' => 'Gagal memeriksa status tugas: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Control a running task (pause/resume/stop)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function controlTask(Request $request)
    {
        $taskId = $request->input('task_id');
        $action = $request->input('action'); // pause, resume, stop
        
        if (!$taskId || !$action) {
            return response()->json(['error' => 'ID tugas dan aksi diperlukan'], 400);
        }
        
        try {
            $task = \App\Models\AutoAnalysisTask::findOrFail($taskId);
            
            switch ($action) {
                case 'pause':
                    $task->status = \App\Models\AutoAnalysisTask::STATUS_PAUSED;
                    $message = 'Tugas analisis otomatis dijeda';
                    break;
                    
                case 'resume':
                    $task->status = \App\Models\AutoAnalysisTask::STATUS_ACTIVE;
                    $message = 'Tugas analisis otomatis dilanjutkan';
                    break;
                    
                case 'stop':
                    $task->status = \App\Models\AutoAnalysisTask::STATUS_COMPLETED;
                    $message = 'Tugas analisis otomatis dihentikan';
                    break;
                    
                default:
                    return response()->json(['error' => 'Aksi tidak valid'], 400);
            }
            
            $task->save();
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'task' => [
                    'id' => $task->id,
                    'status' => $task->status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error controlling task: " . $e->getMessage());
            return response()->json(['error' => 'Gagal mengontrol tugas: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Fallback method to extract content from HTML when Readability fails
     * 
     * @param string $html
     * @return string
     */
    private function extractContentFallback(string $html): string
    {
        // Method 1: Try to find main content container
        $contentSelectors = [
            'article', 
            '.article', 
            '.post-content', 
            '.entry-content', 
            '.content', 
            '.article-content',
            '#article-content',
            '.news-content',
            '.article-body',
            '.article__content',
            '.story-content',
            '.main-content'
        ];
        
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        
        foreach ($contentSelectors as $selector) {
            // Handle both class and ID selectors
            if (strpos($selector, '.') === 0) {
                $className = substr($selector, 1);
                $elements = $xpath->query("//*[contains(@class, '$className')]");
            } else if (strpos($selector, '#') === 0) {
                $id = substr($selector, 1);
                $elements = $xpath->query("//*[@id='$id']");
            } else {
                $elements = $xpath->query("//$selector");
            }
            
            if ($elements && $elements->length > 0) {
                $element = $elements->item(0);
                $content = $dom->saveHTML($element);
                if (strlen($content) > 500) {  // Only return if reasonable length
                    Log::info("Fallback extraction: Found content using selector: $selector");
                    return $content;
                }
            }
        }
        
        // Method 2: Look for paragraphs with reasonable length
        $paragraphs = $xpath->query('//p');
        if ($paragraphs && $paragraphs->length > 0) {
            $content = '';
            foreach ($paragraphs as $p) {
                $paragraphText = $p->textContent;
                if (strlen($paragraphText) > 50) {  // Only include substantial paragraphs
                    $content .= $dom->saveHTML($p);
                }
            }
            
            if (strlen($content) > 500) {
                Log::info("Fallback extraction: Combined paragraphs with length: " . strlen($content));
                return $content;
            }
        }
        
        // Method 3: Last resort - get the body
        $body = $xpath->query('//body');
        if ($body && $body->length > 0) {
            $content = $dom->saveHTML($body->item(0));
            Log::info("Fallback extraction: Using full body with length: " . strlen($content));
            return $content;
        }
        
        return '';
    }
}
