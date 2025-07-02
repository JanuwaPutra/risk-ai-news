<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;
use App\Models\Tokoh;

class AnalysisApiService
{
    private $cache = [];
    
    /**
     * Extract JSON from AI response text
     *
     * @param string $text
     * @return array
     */
    private function extractJsonFromText(string $text): array
    {
        // Try to find JSON object using regex
        $jsonPattern = '/({[^{}]*(?:{[^{}]*})*[^{}]*})/';
        preg_match_all($jsonPattern, $text, $matches);
        
        $validJson = null;
        
        if (!empty($matches[0])) {
            foreach ($matches[0] as $match) {
                try {
                    $decoded = json_decode($match, true);
                    if (is_array($decoded)) {
                        // Validate that it has the expected keys
                        if (isset($decoded['ringkasan']) || isset($decoded['skor_risiko']) || isset($decoded['kategori'])) {
                            $validJson = $decoded;
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // If we found valid JSON, ensure all required fields are present
        if ($validJson) {
            // Set defaults for any missing fields
            $defaults = [
                "ringkasan" => "Tidak ada ringkasan tersedia", 
                "skor_risiko" => 0, 
                "persentase_kerawanan" => "0%", 
                "kategori" => "", 
                "faktor_risiko" => ["Tidak ada faktor risiko teridentifikasi"], 
                "rekomendasi" => "Tidak ada rekomendasi tersedia", 
                "urgensi" => ""
            ];
            
            foreach ($defaults as $key => $value) {
                if (!isset($validJson[$key]) || empty($validJson[$key])) {
                    $validJson[$key] = $value;
                }
            }
            
            // Ensure faktor_risiko is always an array
            if (!is_array($validJson['faktor_risiko'])) {
                if (is_string($validJson['faktor_risiko']) && !empty($validJson['faktor_risiko'])) {
                    $validJson['faktor_risiko'] = [$validJson['faktor_risiko']];
                } else {
                    $validJson['faktor_risiko'] = ["Tidak ada faktor risiko teridentifikasi"];
                }
            }
            
            // Ensure kategori is properly set
            if (empty($validJson['kategori'])) {
                $score = $validJson['skor_risiko'] ?? 0;
                if ($score >= 86) {
                    $validJson['kategori'] = 'KRITIS';
                } elseif ($score >= 61) {
                    $validJson['kategori'] = 'TINGGI';
                } elseif ($score >= 31) {
                    $validJson['kategori'] = 'SEDANG';
                } else {
                    $validJson['kategori'] = 'RENDAH';
                }
            }
            
            // Ensure urgency is properly set based on risk category and score
                            // Urgensi akan diatur otomatis oleh model AnalysisResult
                $validJson['urgensi'] = '';
            
            return $validJson;
        }
        
        // Try to extract JSON from markdown code blocks
        if (strpos($text, '```') !== false) {
            try {
                $codeBlocks = explode('```', $text);
                for ($i = 1; $i < count($codeBlocks); $i += 2) {
                    $block = $codeBlocks[$i];
                    if (strpos($block, 'json') === 0) {
                        $block = substr($block, 4); // Remove 'json' prefix
                    }
                    $block = trim($block);
                    $decoded = json_decode($block, true);
                    if (is_array($decoded)) {
                        // Validate that it has the expected keys
                        if (isset($decoded['ringkasan']) || isset($decoded['skor_risiko']) || isset($decoded['kategori'])) {
                            // Set defaults for any missing fields
                            $defaults = [
                                "ringkasan" => "Tidak ada ringkasan tersedia", 
                                "skor_risiko" => 0, 
                                "persentase_kerawanan" => "0%", 
                                "kategori" => "RENDAH", 
                                "faktor_risiko" => ["Tidak ada faktor risiko teridentifikasi"], 
                                "rekomendasi" => "Tidak ada rekomendasi tersedia", 
                                "urgensi" => ""
                            ];
                            
                            foreach ($defaults as $key => $value) {
                                if (!isset($decoded[$key]) || empty($decoded[$key])) {
                                    $decoded[$key] = $value;
                                }
                            }
                            
                            // Ensure faktor_risiko is always an array
                            if (!is_array($decoded['faktor_risiko'])) {
                                if (is_string($decoded['faktor_risiko']) && !empty($decoded['faktor_risiko'])) {
                                    $decoded['faktor_risiko'] = [$decoded['faktor_risiko']];
                                } else {
                                    $decoded['faktor_risiko'] = ["Tidak ada faktor risiko teridentifikasi"];
                                }
                            }
                            
                            // Ensure kategori is properly set
                            if (empty($decoded['kategori'])) {
                                $score = $decoded['skor_risiko'] ?? 0;
                                if ($score >= 86) {
                                    $decoded['kategori'] = 'KRITIS';
                                } elseif ($score >= 61) {
                                    $decoded['kategori'] = 'TINGGI';
                                } elseif ($score >= 31) {
                                    $decoded['kategori'] = 'SEDANG';
                                } else {
                                    $decoded['kategori'] = 'RENDAH';
                                }
                            }
                            
                            // Urgensi akan diatur otomatis oleh model AnalysisResult
                            $decoded['urgensi'] = '';
                            
                        return $decoded;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue to default return if this fails
                Log::error("Error extracting JSON from code blocks: " . $e->getMessage());
            }
        }
        
        Log::warning("Failed to extract valid JSON from API response. Using default values.");
        
        // Default return if nothing works
        $defaultResult = [
            "ringkasan" => "Tidak dapat mengekstrak analisis yang valid dari respons API", 
            "skor_risiko" => 0, 
            "persentase_kerawanan" => "0%", 
            "kategori" => "RENDAH", 
            "faktor_risiko" => ["Error parsing response"], 
            "rekomendasi" => "Coba lagi nanti", 
            "urgensi" => "MONITORING",
            "nama" => "",
            "jabatan" => "N/A"
        ];
        
        return $defaultResult;
    }
    
    // Metode determineUrgencyFromCategory dihapus karena logika sudah dipindahkan ke model AnalysisResult

    /**
     * Analyze paragraph using AI API
     *
     * @param string $paragraph
     * @param string $personName
     * @param string $personPosition
     * @return array
     */
    public function analyzeParagraph(string $paragraph, string $personName, string $personPosition = ""): array
    {
        // Create cache key based on paragraph and person name
        $cacheKey = md5($paragraph . '-' . $personName);
        
        // Check cache first
        if (isset($this->cache[$cacheKey])) {
            Log::info("Using cached analysis for {$personName}");
            return $this->cache[$cacheKey];
        }
        
        try {
            // Create a seed based on the person's name to get consistent but different responses
            $nameHash = md5($personName);
            $seed = hexdec(substr($nameHash, 0, 8)) % 10000;
            
            // Extract relevant context about the person from the paragraph
            $personNameLower = strtolower($personName);
            $sentences = explode('.', $paragraph);
            $personContext = [];
            
            // Get person's aliases if available
            $aliases = [];
            try {
                $tokoh = \App\Models\Tokoh::where('nama', $personName)->first();
                if ($tokoh && !empty($tokoh->alias)) {
                    $aliases = array_map('trim', preg_split('/[,;\n]+/', $tokoh->alias));
                    $aliases = array_filter($aliases); // Remove empty items
                }
            } catch (\Exception $e) {
                Log::error("Error getting aliases for context: " . $e->getMessage());
            }
            
            foreach ($sentences as $sentence) {
                $sentenceLower = strtolower($sentence);
                
                // Check for name in sentence
                if (str_contains($sentenceLower, $personNameLower)) {
                    $personContext[] = trim($sentence);
                    continue;
                }
                
                // Check for aliases in sentence
                foreach ($aliases as $alias) {
                    if (str_contains($sentenceLower, strtolower($alias))) {
                        $personContext[] = trim($sentence);
                        break;
                    }
                }
            }
            
            // Check if we actually found the person's name in the text with stricter word boundary
            $pattern = '/\b' . preg_quote($personName, '/') . '\b/i';
            $nameFoundWithBoundary = preg_match($pattern, $paragraph, $nameMatches);
            
            if ($nameFoundWithBoundary) {
                Log::info("API Service: Found exact match for name '{$personName}' in text: " . $nameMatches[0]);
            } else {
                Log::info("API Service: No exact match found for name '{$personName}' in text");
            }
            
            // Try to get the person's aliases from the database
            $aliasFoundWithBoundary = false;
            $matchedAlias = "";
            try {
                $tokoh = \App\Models\Tokoh::where('nama', $personName)->first();
                if ($tokoh && !empty($tokoh->alias)) {
                    $aliases = preg_split('/[,;\n]+/', $tokoh->alias);
                    foreach ($aliases as $alias) {
                        $alias = trim($alias);
                        if (!empty($alias)) {
                            $aliasPattern = '/\b' . preg_quote($alias, '/') . '\b/i';
                            if (preg_match($aliasPattern, $paragraph, $aliasMatches)) {
                                $aliasFoundWithBoundary = true;
                                $matchedAlias = $alias;
                                Log::info("API Service: Found exact match for alias '{$alias}' of '{$personName}' in text: " . $aliasMatches[0]);
                                break;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error("API Service: Error checking aliases: " . $e->getMessage());
                // Continue with normal processing
            }
            
            // Double check by testing with character boundaries - super strict test
            $strictNameFound = false;
            if ($nameFoundWithBoundary) {
                // Verify with a super strict test - look for the full name with space or punctuation on both sides
                $superStrictPattern = '/(\s|\.|,|;|:|\?|!|\(|\)|"|\'|^)' . preg_quote($personName, '/') . '(\s|\.|,|;|:|\?|!|\(|\)|"|\'|$)/i';
                if (preg_match($superStrictPattern, $paragraph, $strictMatches)) {
                    $strictNameFound = true;
                    Log::info("API Service: Super strict check PASSED for '{$personName}' with match: " . $strictMatches[0]);
                } else {
                    Log::warning("API Service: Super strict check FAILED for '{$personName}' despite boundary match");
                }
            }
            
            $strictAliasFound = false;
            if ($aliasFoundWithBoundary) {
                // Verify alias with super strict test
                $superStrictAliasPattern = '/(\s|\.|,|;|:|\?|!|\(|\)|"|\'|^)' . preg_quote($matchedAlias, '/') . '(\s|\.|,|;|:|\?|!|\(|\)|"|\'|$)/i';
                if (preg_match($superStrictAliasPattern, $paragraph, $strictAliasMatches)) {
                    $strictAliasFound = true;
                    Log::info("API Service: Super strict check PASSED for alias '{$matchedAlias}' with match: " . $strictAliasMatches[0]);
                } else {
                    Log::warning("API Service: Super strict check FAILED for alias '{$matchedAlias}' despite boundary match");
                }
            }
            
            // Only proceed if either strict name or strict alias is found
            $nameDefinitelyFound = $strictNameFound || $strictAliasFound || $nameFoundWithBoundary || $aliasFoundWithBoundary;
            
            if (!$nameDefinitelyFound && empty($personContext)) {
                // If the name doesn't appear with word boundaries, return a special result
                $notFoundResult = [
                    "ringkasan" => "Tokoh '{$personName}' tidak memiliki pernyataan atau tindakan dalam berita ini karena nama tokoh tidak ditemukan dalam konten berita.", 
                    "skor_risiko" => 0, 
                    "persentase_kerawanan" => "0%", 
                    "kategori" => "RENDAH", 
                    "faktor_risiko" => ["Nama tidak ditemukan dalam berita"], 
                    "rekomendasi" => "Tidak ada rekomendasi karena tokoh tidak ada dalam berita", 
                    "urgensi" => "MONITORING",
                    "nama" => $personName,
                    "jabatan" => $personPosition,
                    "name_not_found" => true // Special flag to indicate name wasn't found
                ];
                
                $this->cache[$cacheKey] = $notFoundResult;
                return $notFoundResult;
            }
            
            $personContextText = !empty($personContext) ? implode('. ', $personContext) : "No specific statements found";
            
            // Get the person's role/position 
            $personRole = !empty($personPosition) ? ", yang merupakan {$personPosition}" : "";
            
            // Make API request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.analysis_api.key'),
                'Content-Type' => 'application/json'
            ])->timeout(15)->post(config('services.analysis_api.url'), [
                'model' => config('services.analysis_api.model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Kamu adalah analis intelijen keamanan senior yang fokus pada Potential Risk Intelligence. Tugasmu adalah mengevaluasi tingkat risiko berdasarkan konten berita dan pernyataan dari tokoh tertentu, serta memberikan rekomendasi yang spesifik untuk peran tokoh tersebut. Balas HANYA dengan JSON, tanpa penjelasan tambahan.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Analisis kutipan berita berikut terkait tokoh '{$personName}'{$personRole} dan berikan penilaian risiko kerawanan/kerusuhan berdasarkan pernyataan dan tindakan tokoh tersebut dalam berita. Fokus pada bagaimana pernyataan atau tindakan tokoh ini mempengaruhi situasi. Peran atau jabatan tokoh: {$personPosition}.\n\nPernyataan atau tindakan tokoh: {$personContextText}\n\nKonteks berita lengkap: {$paragraph}\n\nBalas HANYA dalam format JSON tanpa kalimat pembuka atau penutup:\n{\n \"ringkasan\": \"Ringkasan singkat pernyataan/tindakan tokoh dan potensi dampaknya\",\n \"skor_risiko\": 75,\n \"persentase_kerawanan\": \"75%\",\n \"kategori\": \"TINGGI\",\n \"faktor_risiko\": [\"Faktor 1\", \"Faktor 2\"],\n \"rekomendasi\": \"Rekomendasi tindakan mitigasi yang KHUSUS sesuai peran tokoh '{$personName}' sebagai {$personPosition} dalam isu ini. Rekomendasi harus spesifik dan berbeda dengan tokoh lainnya, berdasarkan jabatan dan pengaruhnya.\",\n \"urgensi\": \"SEGERA\"\n}\n\nKategori harus salah satu dari: RENDAH (0-30%), SEDANG (31-60%), TINGGI (61-85%), KRITIS (86-100%)\nUrgensi harus salah satu dari: MONITORING, PERHATIAN, SEGERA, DARURAT\n\nAnalisis harus spesifik untuk pernyataan/peran tokoh '{$personName}' sebagai {$personPosition} dalam berita ini. Pastikan rekomendasi disesuaikan dengan peran dan jabatan tokoh, bukan rekomendasi umum untuk semua tokoh."
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 300,
                'seed' => $seed
            ]);
            
            if ($response->successful()) {
                $result = $response->json();
                $content = $result['choices'][0]['message']['content'] ?? '';
                
                // Extract JSON from the response
                $analysis = $this->extractJsonFromText($content);
                
                // Initialize nama and jabatan fields
                $analysis['nama'] = $personName;
                $analysis['jabatan'] = $personPosition;
                
                // Make sure faktor_risiko is an array
                if (isset($analysis['faktor_risiko']) && is_string($analysis['faktor_risiko'])) {
                    $analysis['faktor_risiko'] = [$analysis['faktor_risiko']];
                }
                
                // Urgensi akan diatur otomatis oleh model AnalysisResult
                $analysis['urgensi'] = '';
                
                // Cache the result
                $this->cache[$cacheKey] = $analysis;
                
                return $analysis;
            } else {
                Log::error("API request failed: " . $response->body());
                $defaultResponse = [
                    "ringkasan" => "API request failed for {$personName}", 
                    "skor_risiko" => 0, 
                    "persentase_kerawanan" => "0%", 
                    "kategori" => "RENDAH", 
                    "faktor_risiko" => ["Error API"], 
                    "rekomendasi" => "Coba lagi nanti", 
                    "urgensi" => "MONITORING",
                    "nama" => $personName,
                    "jabatan" => $personPosition
                ];
                
                $this->cache[$cacheKey] = $defaultResponse;
                return $defaultResponse;
            }
        } catch (RequestException $e) {
            Log::error("API request timed out: " . $e->getMessage());
            $defaultResponse = [
                "ringkasan" => "API request timed out for {$personName}", 
                "skor_risiko" => 0, 
                "persentase_kerawanan" => "0%", 
                "kategori" => "RENDAH", 
                "faktor_risiko" => ["Timeout"], 
                "rekomendasi" => "Coba lagi nanti", 
                "urgensi" => "MONITORING",
                "nama" => $personName,
                "jabatan" => $personPosition
            ];
            
            $this->cache[$cacheKey] = $defaultResponse;
            return $defaultResponse;
        } catch (\Exception $e) {
            Log::error("Error in API request: " . $e->getMessage());
            $defaultResponse = [
                "ringkasan" => "Error: {$e->getMessage()}", 
                "skor_risiko" => 0, 
                "persentase_kerawanan" => "0%", 
                "kategori" => "RENDAH", 
                "faktor_risiko" => ["Error"], 
                "rekomendasi" => "Periksa koneksi", 
                "urgensi" => "MONITORING",
                "nama" => $personName,
                "jabatan" => $personPosition
            ];
            
            $this->cache[$cacheKey] = $defaultResponse;
            return $defaultResponse;
        }
    }

    /**
     * Analyze content using person ID
     *
     * @param string $content
     * @param int $personId
     * @return array
     */
    public function analyze(string $content, int $personId): array
    {
        try {
            // Get person details from ID
            $person = Tokoh::find($personId);
            if (!$person) {
                Log::error("Person ID {$personId} not found in database");
                return [
                    "ringkasan" => "Tokoh tidak ditemukan", 
                    "skor_risiko" => 0, 
                    "persentase_kerawanan" => "0%", 
                    "kategori" => "RENDAH", 
                    "faktor_risiko" => ["ID tidak valid"], 
                    "rekomendasi" => "Verifikasi ID tokoh", 
                    "urgensi" => "",
                    "nama" => "Unknown",
                    "jabatan" => "Unknown"
                ];
            }
            
            $personName = $person->nama;
            $personPosition = $person->jabatan ?? "";
            
            // Forward to the existing method
            return $this->analyzeParagraph($content, $personName, $personPosition);
        } catch (\Exception $e) {
            Log::error("Error in analyze method: " . $e->getMessage());
            return [
                "ringkasan" => "Terjadi kesalahan dalam analisis", 
                "skor_risiko" => 0, 
                "persentase_kerawanan" => "0%", 
                "kategori" => "RENDAH", 
                "faktor_risiko" => ["Error: " . $e->getMessage()], 
                "rekomendasi" => "Coba lagi", 
                "urgensi" => "",
                "nama" => "Error",
                "jabatan" => "Error"
            ];
        }
    }
} 