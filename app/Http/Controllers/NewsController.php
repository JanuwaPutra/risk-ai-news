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
use Goose\Client as GooseClient;
use Pforret\PfArticleExtractor\ArticleExtractor;

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
            // Fetch full article content
            try {
                // Special handling for problematic sites
                if (strpos($url, 'detik.com') !== false || strpos($url, 'inews.id') !== false || strpos($url, 'liputan6.com') !== false) {
                    // Use specialized headers and longer timeout for these sites
                    $response = Http::timeout(20)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                            'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8',
                            'Cache-Control' => 'no-cache',
                            'Connection' => 'keep-alive',
                            'Pragma' => 'no-cache',
                            'DNT' => '1'  // Do Not Track
                        ])
                        ->get($url);
                    
                    // If response fails, try with curl as a last resort
                    if (!$response->successful()) {
                        Log::warning("HTTP request failed for {$url}, trying with curl");
                        
                        // Initialize curl
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
                        
                        $html = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode >= 400 || empty($html)) {
                            return response()->json(['error' => "Failed to fetch article: HTTP {$httpCode}"], 500);
                        }
                    } else {
                        $html = $response->body();
                    }
                } else {
                    // Normal handling for other sites
                    $response = Http::timeout(15)->get($url);
                
                if (!$response->successful()) {
                    Log::error("Failed to fetch article from URL: {$url}, status code: {$response->status()}");
                    return response()->json(['error' => "Failed to fetch article: HTTP {$response->status()}"], 500);
                }
                
                $html = $response->body();
                }
                
                if (empty($html)) {
                    Log::error("Empty response from URL: {$url}");
                    return response()->json(['error' => 'Empty response from URL'], 500);
                }
            } catch (\Exception $e) {
                Log::error("Exception fetching URL {$url}: " . $e->getMessage());
                return response()->json(['error' => "Failed to fetch article: {$e->getMessage()}"], 500);
            }
            
            // Use multi-extractor approach to get the best content
            $extractionResult = $this->extractContentWithMultipleLibraries($html, $url);
            
            if ($extractionResult['success']) {
                return response()->json([
                    'title' => $extractionResult['title'],
                    'content' => $extractionResult['content'],
                    'excerpt' => $extractionResult['excerpt'],
                    'method' => $extractionResult['method']
                ]);
            } else {
                // For problematic sites, create a basic content with just the title
                if (strpos($url, 'detik.com') !== false || strpos($url, 'inews.id') !== false || strpos($url, 'liputan6.com') !== false) {
                    // Try to extract title from HTML
                    $titleMatch = [];
                    preg_match('/<title>(.*?)<\/title>/i', $html, $titleMatch);
                    $title = !empty($titleMatch[1]) ? $titleMatch[1] : 'Article';
                    
                    // Try to extract meta description
                    $descMatch = [];
                    preg_match('/<meta\s+name=["|\']description["|\'][^>]*content=["|\']([^>]*?)["|\'][^>]*>/i', $html, $descMatch);
                    $description = !empty($descMatch[1]) ? $descMatch[1] : '';
                    
                    // Create a basic content with title and description
                    $content = "<div class='extracted-text'>";
                    $content .= "<h1>{$title}</h1>";
                    if (!empty($description)) {
                        $content .= "<p>{$description}</p>";
                    }
                    $content .= "<p class='alert alert-warning'>Konten lengkap tidak dapat diambil dari situs ini. Silakan kunjungi <a href='{$url}' target='_blank'>situs asli</a> untuk membaca artikel lengkap.</p>";
                    $content .= "</div>";
                    
                    return response()->json([
                        'title' => $title,
                        'content' => $content,
                        'excerpt' => $description ?: "Konten tidak dapat diambil secara lengkap.",
                        'method' => 'basic-fallback'
                    ]);
                }
            
                        Log::error("All content extraction methods failed for URL: {$url}");
                return response()->json(['error' => "Failed to parse article content. This may be due to the website structure or anti-scraping measures."], 500);
                    }
                    
        } catch (\Exception $e) {
            Log::error("Error fetching full article: " . $e->getMessage());
            return response()->json(['error' => 'Failed to parse content: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Extract content using multiple libraries for better results
     * 
     * @param string $html
     * @param string $url
     * @return array
     */
    private function extractContentWithMultipleLibraries(string $html, string $url): array
    {
        $result = [
            'success' => false,
            'title' => '',
            'content' => '',
            'excerpt' => '',
            'method' => ''
        ];
        
        // Method 1: Laravel-Readability (the-3labs-team/laravel-readability)
        try {
            Log::info("Trying Laravel-Readability extraction for URL: {$url}");
            $parsed = \Readability::parse($html);
            
            if ($parsed && !empty($parsed->getContent())) {
                $content = $parsed->getContent();
                $title = $parsed->getTitle();
                
                // Check if content is substantial
                if (strlen(strip_tags($content)) > 400) {
                    Log::info("Laravel-Readability extraction successful for URL: {$url}");
                    return [
                        'success' => true,
                        'title' => $title,
                        'content' => $content,
                        'excerpt' => substr(strip_tags($content), 0, 150) . '...',
                        'method' => 'laravel-readability'
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Laravel-Readability extraction failed for URL {$url}: " . $e->getMessage());
                }
                
        // Method 2: PHP-Goose (scotteh/php-goose)
        try {
            Log::info("Trying PHP-Goose extraction for URL: {$url}");
            $goose = new GooseClient([
                'language' => 'id',
                'image_fetch_best' => true,
                'browser_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]);
            
            try {
                $article = $goose->extractContent($url);
                $content = $article->getCleanedArticleText();
                $title = $article->getTitle();
                
                // Check if content is substantial
                if (!empty($content) && strlen($content) > 400) {
                    // Format the content as HTML
                    $formattedContent = '<div class="goose-content">';
                    $contentParagraphs = explode("\n", $content);
                    foreach ($contentParagraphs as $paragraph) {
                        if (!empty(trim($paragraph))) {
                            $formattedContent .= "<p>{$paragraph}</p>";
                        }
                    }
                    $formattedContent .= '</div>';
                    
                    // Add the main image if available
                    try {
                        $mainImage = $article->getTopImage();
                        if ($mainImage) {
                            $imageUrl = $mainImage->getImageSrc();
                            if (!empty($imageUrl)) {
                                $formattedContent = "<div class='article-image'><img src='{$imageUrl}' class='img-fluid' alt='{$title}'></div>" . $formattedContent;
                }
                        }
                    } catch (\Exception $e) {
                        Log::warning("Error getting top image for URL {$url}: " . $e->getMessage());
                        // Continue without the image
                    }
                    
                    Log::info("PHP-Goose extraction successful for URL: {$url}");
                    return [
                        'success' => true,
                    'title' => $title,
                        'content' => $formattedContent,
                        'excerpt' => substr(strip_tags($content), 0, 150) . '...',
                        'method' => 'php-goose'
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("PHP-Goose content extraction failed for URL {$url}: " . $e->getMessage());
                // Continue to next method
            }
        } catch (\Exception $e) {
            Log::warning("PHP-Goose initialization failed for URL {$url}: " . $e->getMessage());
        }
        
        // Method 3: Pf-Article-Extractor (pforret/pf-article-extractor)
        try {
            Log::info("Trying Pf-Article-Extractor for URL: {$url}");
            $data = ArticleExtractor::getArticle($url);
            
            if ($data && !empty($data->content)) {
                $content = $data->content;
                $title = $data->title ?? '';
                
                // Check if content is substantial
                if (strlen(strip_tags($content)) > 300) {
                    Log::info("Pf-Article-Extractor successful for URL: {$url}");
                    return [
                        'success' => true,
                        'title' => $title,
                        'content' => $content,
                        'excerpt' => substr(strip_tags($content), 0, 150) . '...',
                        'method' => 'pf-article-extractor'
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Pf-Article-Extractor failed for URL {$url}: " . $e->getMessage());
        }
        
        // Method 4: Original Readability (fivefilters/readability)
        try {
            Log::info("Trying original Readability for URL: {$url}");
            $configuration = new Configuration();
            $configuration->setFixRelativeURLs(true);
            $configuration->setOriginalURL($url);
            
            $readability = new Readability($configuration);
            $result = $readability->parse($html);
            
            if ($result && !empty($readability->getContent())) {
                $content = $readability->getContent();
                $title = $readability->getTitle();
                
                // Check if content is substantial
                if (strlen(strip_tags($content)) > 300) {
                    Log::info("Original Readability extraction successful for URL: {$url}");
                    return [
                        'success' => true,
                        'title' => $title,
                        'content' => $content,
                        'excerpt' => $readability->getExcerpt() ?: substr(strip_tags($content), 0, 150) . '...',
                        'method' => 'original-readability'
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Original Readability extraction failed for URL {$url}: " . $e->getMessage());
        }
        
        // Method 5: Our custom fallback extractor
        try {
            Log::info("Trying custom fallback extractor for URL: {$url}");
            $content = $this->extractContentFallback($html);
            
            if (!empty($content) && strlen(strip_tags($content)) > 300) {
                // Try to extract a title from the HTML
                $titleMatch = [];
                preg_match('/<title>(.*?)<\/title>/i', $html, $titleMatch);
                $title = !empty($titleMatch[1]) ? $titleMatch[1] : 'Article';
                
                Log::info("Custom fallback extraction successful for URL: {$url}");
                return [
                    'success' => true,
                    'title' => $title,
                    'content' => $content,
                    'excerpt' => substr(strip_tags($content), 0, 150) . '...',
                    'method' => 'custom-fallback'
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Custom fallback extraction failed for URL {$url}: " . $e->getMessage());
        }
        
        // If all methods failed, return failure
        return $result;
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
            
            // Ensure faktor_risiko is properly handled
            $faktorRisiko = $analysis['faktor_risiko'] ?? [];
            if (is_string($faktorRisiko)) {
                // If it's a string, convert to array with one item
                $faktorRisiko = [$faktorRisiko];
            } elseif (empty($faktorRisiko)) {
                // If it's empty, provide a default
                $faktorRisiko = ['Tidak ada faktor risiko teridentifikasi'];
            } elseif (!is_array($faktorRisiko)) {
                // If it's neither string nor array, convert to string and then to array
                $faktorRisiko = [(string)$faktorRisiko];
            }
            
            // Convert faktor_risiko to string for storage if needed
            $faktorRisikoStr = is_array($faktorRisiko) ? implode(', ', $faktorRisiko) : (string)$faktorRisiko;
            
            // Save result to database
            try {
            $analysisResult = AnalysisResult::updateOrCreate(
                [
                    'nama' => $person->nama,
                    'paragraf' => substr($plainText, 0, 1000), // Store first 1000 chars
                ],
                [
                    'jabatan' => $person->jabatan,
                    'source' => $source,
                        'url' => $url, // Add URL field
                    'kata_kunci' => $searchQuery,
                    'ringkasan' => $analysis['ringkasan'] ?? 'N/A',
                    'skor_risiko' => $analysis['skor_risiko'] ?? 0,
                    'persentase_kerawanan' => $analysis['persentase_kerawanan'] ?? '0%',
                    'kategori' => $analysis['kategori'] ?? 'RENDAH',
                    'faktor_risiko' => $faktorRisikoStr,
                    'rekomendasi' => $analysis['rekomendasi'] ?? 'N/A',
                    'urgensi' => $analysis['urgensi'] ,
                    'tanggal_tambah' => now()
                ]
            );
            
            return response()->json([
                'success' => true,
                'analysis' => $analysis,
                'result_id' => $analysisResult->id
            ]);
            } catch (\Exception $dbException) {
                Log::error("Database error saving analysis result: " . $dbException->getMessage());
                
                // Return success with analysis even if DB save failed
                return response()->json([
                    'success' => true,
                    'analysis' => $analysis,
                    'warning' => 'Analysis completed but could not be saved to database',
                    'db_error' => $dbException->getMessage()
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error("Error analyzing article: " . $e->getMessage());
            return response()->json(['error' => 'Failed to analyze article: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Analyze all articles for all people
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzeArticleForAll(Request $request)
    {
        $url = $request->input('url');
        $title = $request->input('title', '');
        $source = $request->input('source', '');
        $searchQuery = $request->input('search_query', '');
        $titleOnly = $request->input('title_only', false);
        
        try {
            // If URL is not provided, return error
            if (empty($url)) {
            return response()->json(['error' => 'URL is required'], 400);
        }
        
            // Fetch all tokoh data
            $people = Tokoh::all();
            
            // If no people found, return error
            if ($people->isEmpty()) {
                return response()->json(['error' => 'No people found in the database'], 404);
            }
            
            // For title-only mode, skip content fetching entirely
            if ($titleOnly) {
                Log::info("Using title-only mode for URL: {$url}");
                
                // Create more contextual synthetic content from title, source, and URL
                $syntheticContent = "Artikel berita dengan judul: {$title}.";
                $syntheticContent .= " Artikel ini diterbitkan oleh {$source}.";
                $syntheticContent .= " URL artikel: {$url}.";
                
                if (!empty($searchQuery)) {
                    $syntheticContent .= " Artikel ini terkait dengan kata kunci pencarian: {$searchQuery}.";
                }
                
                // Add more context about the possible topic based on the title
                $syntheticContent .= " Artikel ini membahas tentang " . $title . ".";
                
                Log::info("Generated synthetic content for title-only mode: " . substr($syntheticContent, 0, 100) . "...");
                $content = $syntheticContent;
            } else {
                // Regular processing with content extraction
                try {
                    // For detik.com and inews.id, use special handling
                    if (strpos($url, 'detik.com') !== false || strpos($url, 'inews.id') !== false || strpos($url, 'liputan6.com') !== false || strpos($url, 'suara.com') !== false) {
                        // Use longer timeout for these sites
                        $response = Http::timeout(20)->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                            'Accept' => 'text/html,application/xhtml+xml,application/xml',
                            'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8'
                        ])->get($url);
                    } else {
                        // Normal handling for other sites
                        $response = Http::timeout(15)->get($url);
                    }
                
                if (!$response->successful()) {
                    Log::error("Failed to fetch article from URL: {$url}, status code: {$response->status()}");
                        
                        // Fall back to title-only if HTTP request fails
                        $syntheticContent = "Artikel berita: {$title}";
                        if (!empty($source)) {
                            $syntheticContent .= " (Sumber: {$source})";
                        }
                        if (!empty($searchQuery)) {
                            $syntheticContent .= " Kata kunci pencarian: {$searchQuery}";
                }
                
                        $content = $syntheticContent;
                    } else {
                $html = $response->body();
                
                if (empty($html)) {
                    Log::error("Empty response from URL: {$url}");
                            throw new \Exception("Empty response from URL");
                }
                        
                        // Use multiple extractors to get content
                        $extractionResult = $this->extractContentWithMultipleLibraries($html, $url);
                        
                        if (!$extractionResult['success']) {
                            // Try direct text extraction for sites that block scrapers
                            $plainText = $this->extractPlainText($html);
                            
                            if (empty($plainText) || strlen($plainText) < 200) {
                                Log::error("Failed to extract plain text from URL: {$url}");
                    
                                // Fall back to synthetic content from title
                                $syntheticContent = "Artikel berita: {$title}";
                                if (!empty($source)) {
                                    $syntheticContent .= " (Sumber: {$source})";
                                }
                                if (!empty($searchQuery)) {
                                    $syntheticContent .= " Kata kunci pencarian: {$searchQuery}";
                                }
                                
                                $content = $syntheticContent;
                            } else {
                                $content = $plainText;
                    }
                } else {
                            $content = strip_tags($extractionResult['content']);
                        }
                }
            } catch (\Exception $e) {
                    Log::error("Exception fetching URL {$url}: " . $e->getMessage());
                
                    // Fall back to title-only if there's any exception
                    $syntheticContent = "Artikel berita: {$title}";
                    if (!empty($source)) {
                        $syntheticContent .= " (Sumber: {$source})";
                    }
                    if (!empty($searchQuery)) {
                        $syntheticContent .= " Kata kunci pencarian: {$searchQuery}";
                }
                
                    $content = $syntheticContent;
                }
            }
            
            // Always ensure we have some content, even if it's minimal
            if (empty($content) || strlen($content) < 50) {
                $content = "Artikel berita: {$title} (Sumber: {$source})";
            }
            
            // Results array
            $results = [];
            $foundPeople = [];
            
            // For each person, analyze the content
            foreach ($people as $person) {
                // Check if person name is mentioned in the content
                $name = $person->nama;
                $jabatan = $person->jabatan;
                    $nameFound = false;
                    $aliasFound = false;
                
                // First check in title - more reliable
                if (stripos($title, $name) !== false) {
                    $nameFound = true;
                }
                
                // Check alias in title
                    if (!$nameFound && !empty($person->alias)) {
                        $aliases = preg_split('/[,;\n]+/', $person->alias);
                        foreach ($aliases as $alias) {
                            $alias = trim($alias);
                        if (!empty($alias) && stripos($title, $alias) !== false) {
                                    $aliasFound = true;
                                    break;
                                }
                        }
                    }
                    
                // If not found in title, check in content
                if (!$nameFound && !$aliasFound) {
                    if (stripos($content, $name) !== false) {
                        $nameFound = true;
                        }
                        
                    // Check alias in content
                    if (!$nameFound && !empty($person->alias)) {
                            $aliases = preg_split('/[,;\n]+/', $person->alias);
                            foreach ($aliases as $alias) {
                                $alias = trim($alias);
                            if (!empty($alias) && stripos($content, $alias) !== false) {
                                $aliasFound = true;
                                        break;
                                    }
                                }
                            }
                        }
                        
                // Check if person name is mentioned
                if ($nameFound || $aliasFound) {
                    // Keep track of found people
                    $foundPeople[] = $name;
                    
                    // Analyze the content for this person
                    try {
                        $analysisResult = $this->analysisApi->analyze($content, $person->id);
                        
                        if (!$analysisResult || !isset($analysisResult['skor_risiko'])) {
                            Log::error("Invalid analysis result for person {$person->nama} on URL {$url}");
                            continue;
                        }
                        
                        // Ensure faktor_risiko is properly handled
                        $faktorRisiko = $analysisResult['faktor_risiko'] ?? [];
                        if (is_string($faktorRisiko)) {
                            // If it's a string, convert to array with one item
                            $faktorRisiko = [$faktorRisiko];
                        } elseif (empty($faktorRisiko)) {
                            // If it's empty, provide a default
                            $faktorRisiko = ['Tidak ada faktor risiko teridentifikasi'];
                        } elseif (!is_array($faktorRisiko)) {
                            // If it's neither string nor array, convert to string and then to array
                            $faktorRisiko = [(string)$faktorRisiko];
                        }
                        
                        // Convert faktor_risiko to string for storage if needed
                        $faktorRisikoStr = is_array($faktorRisiko) ? implode(', ', $faktorRisiko) : (string)$faktorRisiko;
                        
                        // Log the analysis results
                        Log::info("Analysis for {$person->nama}: Category={$analysisResult['kategori']}, Score={$analysisResult['skor_risiko']}, Urgency={$analysisResult['urgensi']}");
                        
                        // Save the analysis result with proper error handling
                        try {
                            $result = new AnalysisResult();
                            $result->nama = $person->nama;
                            $result->jabatan = $person->jabatan ?? '';
                            $result->skor_risiko = $analysisResult['skor_risiko'];
                            $result->kategori = $analysisResult['kategori'];
                            $result->rekomendasi = $analysisResult['rekomendasi'] ?? 'Tidak ada rekomendasi';
                            $result->persentase_kerawanan = $analysisResult['persentase_kerawanan'];
                            $result->paragraf = $title ?: substr($content, 0, 255);
                            $result->source = $source;
                            $result->url = $url;
                            $result->ringkasan = $analysisResult['ringkasan'] ?? 'Tidak ada ringkasan';
                            $result->faktor_risiko = $faktorRisikoStr;
                            if (!empty($searchQuery)) {
                                $result->kata_kunci = $searchQuery;
                            }
                            
                            // Add task_id if present in the request
                            if ($request->has('task_id')) {
                                $result->task_id = $request->input('task_id');
                            }
                            
                            $result->save();
                        
                        $results[] = [
                                'id' => $result->id,
                            'name' => $person->nama,
                                'position' => $person->jabatan ?? '',
                                'score' => $analysisResult['skor_risiko'],
                                'category' => $analysisResult['kategori'],
                                'recommendation' => $analysisResult['rekomendasi'] ?? 'Tidak ada rekomendasi',
                                'percentage' => $analysisResult['persentase_kerawanan'],
                                'summary' => $analysisResult['ringkasan'] ?? 'Tidak ada ringkasan'
                            ];
                        } catch (\Exception $dbException) {
                            Log::error("Error saving analysis result for person {$person->nama}: " . $dbException->getMessage());
                            // Continue processing other people instead of failing the whole request
                            continue;
                    }
                } catch (\Exception $e) {
                        Log::error("Error analyzing content for person {$person->nama}: " . $e->getMessage());
                        continue;
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'results' => $results,
                'article_title' => $title,
                'article_source' => $source,
                'people_mentioned' => count($results),
                'found_people' => $foundPeople,
                'title_only_mode' => $titleOnly
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error in analyzeArticleForAll: " . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
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
                            
                            // Ensure faktor_risiko is properly handled
                            $faktorRisiko = $analysis['faktor_risiko'] ?? [];
                            if (is_string($faktorRisiko)) {
                                // If it's a string, convert to array with one item
                                $faktorRisiko = [$faktorRisiko];
                            } elseif (empty($faktorRisiko)) {
                                // If it's empty, provide a default
                                $faktorRisiko = ['Tidak ada faktor risiko teridentifikasi'];
                            } elseif (!is_array($faktorRisiko)) {
                                // If it's neither string nor array, convert to string and then to array
                                $faktorRisiko = [(string)$faktorRisiko];
                            }
                            
                            // Convert faktor_risiko to string for storage if needed
                            $faktorRisikoStr = is_array($faktorRisiko) ? implode(', ', $faktorRisiko) : (string)$faktorRisiko;
                            
                            // Log the analysis results
                            Log::info("Analysis for {$person->nama}: Category={$analysis['kategori']}, Score={$analysis['skor_risiko']}, Urgency={$analysis['urgensi']}");
                            
                            // Save result to database
                            // Log analysis details before saving
                            Log::info("About to save analysis for {$person->nama}: Category={$analysis['kategori']}, Score={$analysis['skor_risiko']}");
                            
                            $analysisResult = AnalysisResult::updateOrCreate(
                                [
                                    'nama' => $person->nama,
                                    'paragraf' => substr($plainText, 0, 1000), // Store first 1000 chars
                                    'task_id' => $task->id,
                                ],
                                [
                                    'jabatan' => $person->jabatan,
                                    'source' => $source,
                                    'url' => $url, // Add URL field
                                    'kata_kunci' => $query,
                                    'ringkasan' => $analysis['ringkasan'] ?? 'N/A',
                                    'skor_risiko' => $analysis['skor_risiko'] ?? 0,
                                    'persentase_kerawanan' => $analysis['persentase_kerawanan'] ?? '0%',
                                    'kategori' => $analysis['kategori'] ?? 'RENDAH',
                                    'faktor_risiko' => $faktorRisikoStr,
                                    'rekomendasi' => $analysis['rekomendasi'] ?? 'N/A',
                                    'urgensi' => $analysis['urgensi'],
                                    'tanggal_tambah' => now()
                                ]
                            );
                            
                            // Verify the saved urgency
                            Log::info("Saved analysis ID {$analysisResult->id} with urgency: {$analysisResult->urgensi}");
                            
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
        // Method 1: Try to find main content container based on common and Indonesia-specific news sites
        $contentSelectors = [
            // Common selectors
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
            '.main-content',
            
            // Indonesian news sites specific selectors
            '.l-container',         // CNN Indonesia
            '.read__content',       // Detik
            '.itp_bodycontent',     // Detik variants
            '.detail-text',         // Kompas
            '.detail__body-text',   // Kompas.com
            '.detail-in',           // Tribunnews
            '.dtl_content',         // SuaraMerdeka
            '.main-content-detail', // Liputan6
            '.article-content-body', // Tempo
            '.content-article',     // Kalbaronline
            '.press-release',       // GlobeNewswire
            '.kcm-read-content',    // Kompas.tv
            '.detail-artikel',      // Suara.com
            '.detail-desk',         // Suaraindonesia-news
            '.post__content',       // Various WordPress-based news sites
            '.elementor-widget-container' // Many Indonesian news sites use Elementor
        ];
        
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);
        
        // Try each selector
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
                    return $this->cleanupContent($content);
                }
            }
        }
        
        // Method 2: Look for article URL patterns and apply specific extractors
        $url = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($url)) {
            // Site-specific content extraction based on URL patterns
            if (strpos($url, 'detik.com') !== false) {
                $detikContent = $this->extractDetikContent($dom, $xpath);
                if (!empty($detikContent)) return $detikContent;
            } else if (strpos($url, 'kompas.com') !== false || strpos($url, 'kompas.tv') !== false) {
                $kompasContent = $this->extractKompasContent($dom, $xpath);
                if (!empty($kompasContent)) return $kompasContent;
            } else if (strpos($url, 'globenewswire.com') !== false) {
                $gnwContent = $this->extractGlobeNewswireContent($dom, $xpath);
                if (!empty($gnwContent)) return $gnwContent;
            } else if (strpos($url, 'kalbaronline.com') !== false) {
                $kalbarContent = $this->extractKalbarOnlineContent($dom, $xpath);
                if (!empty($kalbarContent)) return $kalbarContent;
            }
        }
        
        // Method 3: Look for paragraphs with reasonable length
        $paragraphs = $xpath->query('//p');
        if ($paragraphs && $paragraphs->length > 0) {
            $content = '';
            $validParagraphs = 0;
            
            foreach ($paragraphs as $p) {
                $paragraphText = $p->textContent;
                // Only include substantial paragraphs
                if (strlen(trim($paragraphText)) > 30) {
                    $content .= $dom->saveHTML($p);
                    $validParagraphs++;
                }
            }
            
            if (strlen($content) > 500 && $validParagraphs >= 3) {
                Log::info("Fallback extraction: Combined paragraphs with length: " . strlen($content));
                return "<div class='extracted-content'>{$content}</div>";
            }
        }
        
        // Method 4: Look for divs with text content
        $textDivs = $xpath->query('//div[string-length(text()) > 100]');
        if ($textDivs && $textDivs->length > 0) {
            $bestDiv = null;
            $maxLength = 0;
            
            foreach ($textDivs as $div) {
                $text = $div->textContent;
                $length = strlen(trim($text));
                
                if ($length > $maxLength && $length > 200) {
                    $maxLength = $length;
                    $bestDiv = $div;
                }
            }
            
            if ($bestDiv !== null) {
                $content = $dom->saveHTML($bestDiv);
                Log::info("Fallback extraction: Found content div with length: " . $maxLength);
                return $this->cleanupContent($content);
            }
        }
        
        // Method 5: Last resort - get the body and try to clean it up
        $body = $xpath->query('//body');
        if ($body && $body->length > 0) {
            $content = $dom->saveHTML($body->item(0));
            Log::info("Fallback extraction: Using full body with length: " . strlen($content));
            return $this->cleanupContent($content);
        }
        
        return '';
    }
    
    /**
     * Clean up extracted content by removing unwanted elements
     *
     * @param string $content
     * @return string
     */
    private function cleanupContent(string $content): string
    {
        // Create DOM from the content
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);
        
        // Remove unwanted elements
        $removeSelectors = [
            '//script',
            '//style',
            '//iframe',
            '//nav',
            '//header',
            '//footer',
            '//form',
            '//*[contains(@class, "comment")]',
            '//*[contains(@class, "sidebar")]',
            '//*[contains(@class, "widget")]',
            '//*[contains(@class, "related")]',
            '//*[contains(@class, "share")]',
            '//*[contains(@class, "social")]',
            '//*[contains(@class, "menu")]',
            '//*[contains(@class, "navigation")]',
            '//*[contains(@class, "newsletter")]',
            '//*[contains(@class, "ad")]',
            '//*[contains(@class, "popup")]',
            '//*[contains(@class, "banner")]'
        ];
        
        foreach ($removeSelectors as $selector) {
            $elements = $xpath->query($selector);
            if ($elements) {
                foreach ($elements as $element) {
                    if ($element->parentNode) {
                        $element->parentNode->removeChild($element);
                    }
                }
            }
        }
        
        // Get body content
        $bodies = $xpath->query('//body');
        if ($bodies->length > 0) {
            $body = $bodies->item(0);
            $cleanContent = '';
            
            // Extract only the paragraphs and headings
            $contentNodes = $xpath->query('.//p|.//h1|.//h2|.//h3|.//h4|.//h5|.//h6|.//img', $body);
            foreach ($contentNodes as $node) {
                $cleanContent .= $dom->saveHTML($node);
            }
            
            if (strlen($cleanContent) > 200) {
                return "<div class='extracted-content'>{$cleanContent}</div>";
            }
            
            // If not enough content found, return the cleaned body
            return $dom->saveHTML($body);
        }
        
            return $content;
    }
    
    /**
     * Extract content specifically from Detik.com
     *
     * @param \DOMDocument $dom
     * @param \DOMXPath $xpath
     * @return string
     */
    private function extractDetikContent(\DOMDocument $dom, \DOMXPath $xpath): string
    {
        // First try with common selectors
        $selectors = [
            '//div[contains(@class, "detail__body-text")]',
            '//div[contains(@class, "itp_bodycontent")]',
            '//div[contains(@class, "detail_text")]'
        ];
        
        foreach ($selectors as $selector) {
            $elements = $xpath->query($selector);
            if ($elements && $elements->length > 0) {
                $content = $dom->saveHTML($elements->item(0));
                if (strlen($content) > 300) {
                    return $this->cleanupContent($content);
                }
            }
        }
        
        // Special handling for health.detik.com which has different structure
        $healthSelectors = [
            '//article',
            '//div[contains(@class, "paradetail")]',
            '//div[contains(@class, "itp-article")]',
            '//div[contains(@class, "detail-text")]',
            '//div[contains(@data-component, "article")]'
        ];
        
        foreach ($healthSelectors as $selector) {
            $elements = $xpath->query($selector);
            if ($elements && $elements->length > 0) {
                // Extract all paragraphs from this element
                $paragraphs = $xpath->query('.//p', $elements->item(0));
                $content = '<div class="detik-extracted">';
                
                if ($paragraphs && $paragraphs->length > 0) {
                    foreach ($paragraphs as $p) {
                        $text = trim($p->textContent);
                        if (strlen($text) > 20) {  // Only include substantial paragraphs
                            $content .= "<p>{$text}</p>";
                        }
                    }
                    $content .= '</div>';
                    
                    if (strlen($content) > 300) {
                        return $content;
                    }
                }
                
                // If paragraphs didn't work, try getting the inner HTML directly
                $content = $dom->saveHTML($elements->item(0));
                // Remove scripts and other problematic elements
                $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
                $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
                
                if (strlen(strip_tags($content)) > 200) {
                    return $this->cleanupContent($content);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract content specifically from Kompas.com and Kompas.tv
     *
     * @param \DOMDocument $dom
     * @param \DOMXPath $xpath
     * @return string
     */
    private function extractKompasContent(\DOMDocument $dom, \DOMXPath $xpath): string
    {
        $selectors = [
            '//div[contains(@class, "read__content")]',
            '//div[contains(@class, "kcm-read-content")]',
            '//div[contains(@class, "article__body")]'
        ];
        
        foreach ($selectors as $selector) {
            $elements = $xpath->query($selector);
            if ($elements && $elements->length > 0) {
                $content = $dom->saveHTML($elements->item(0));
                if (strlen($content) > 300) {
                    return $this->cleanupContent($content);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract content specifically from GlobeNewswire
     *
     * @param \DOMDocument $dom
     * @param \DOMXPath $xpath
     * @return string
     */
    private function extractGlobeNewswireContent(\DOMDocument $dom, \DOMXPath $xpath): string
    {
        $selectors = [
            '//div[contains(@class, "press-release")]',
            '//div[contains(@class, "release-body")]'
        ];
        
        foreach ($selectors as $selector) {
            $elements = $xpath->query($selector);
            if ($elements && $elements->length > 0) {
                $content = $dom->saveHTML($elements->item(0));
                if (strlen($content) > 300) {
                    return $this->cleanupContent($content);
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract content specifically from KalbarOnline
     *
     * @param \DOMDocument $dom
     * @param \DOMXPath $xpath
     * @return string
     */
    private function extractKalbarOnlineContent(\DOMDocument $dom, \DOMXPath $xpath): string
    {
        $selectors = [
            '//div[contains(@class, "entry-content")]',
            '//div[contains(@class, "content-article")]'
        ];
        
        foreach ($selectors as $selector) {
            $elements = $xpath->query($selector);
            if ($elements && $elements->length > 0) {
                $content = $dom->saveHTML($elements->item(0));
                if (strlen($content) > 300) {
                    return $this->cleanupContent($content);
                }
            }
        }
        
        return '';
    }

    /**
     * Extract plain text from HTML as a last resort
     * 
     * @param string $html
     * @return string
     */
    private function extractPlainText(string $html): string
    {
        // Remove script tags first
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        
        // Remove HTML tags
        $text = strip_tags($html);
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        
        // Get the most substantial paragraph
        $paragraphs = preg_split('/\n+|\r+|\r\n+|\.\s+/', $text);
        $bestParagraph = '';
        $maxLength = 0;
        
        foreach ($paragraphs as $paragraph) {
            $trimmed = trim($paragraph);
            $length = strlen($trimmed);
            if ($length > $maxLength && $length > 50) {
                $maxLength = $length;
                $bestParagraph = $trimmed;
            }
        }
        
        if (!empty($bestParagraph)) {
            return $bestParagraph;
        }
        
        // If no good paragraph found, return some content from the middle
        if (strlen($text) > 500) {
            $middle = (int)(strlen($text) / 2);
            $start = $middle - 250;
            if ($start < 0) $start = 0;
            return substr($text, $start, 500);
        }
        
        return $text;
    }

    /**
     * Determine urgency level based on risk category and score
     *
     * @param string $category
     * @param int $score
     * @return string
     */
    // Metode ini dihapus karena logika sudah dipindahkan ke model AnalysisResult
}
