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
        
        if (!empty($matches[0])) {
            foreach ($matches[0] as $match) {
                try {
                    return json_decode($match, true) ?: [];
                } catch (\Exception $e) {
                    continue;
                }
            }
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
                    if ($decoded) {
                        return $decoded;
                    }
                }
            } catch (\Exception $e) {
                // Continue to default return if this fails
            }
        }
        
        // Default return if nothing works
        return [
            "ringkasan" => "Failed to extract JSON", 
            "skor_risiko" => 0, 
            "persentase_kerawanan" => "0%", 
            "kategori" => "RENDAH", 
            "faktor_risiko" => ["Error parsing"], 
            "rekomendasi" => "Coba lagi", 
            "urgensi" => "MONITORING",
            "nama" => "",
            "jabatan" => "N/A"
        ];
    }
    
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
                'Authorization' => 'Bearer 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',
                'Content-Type' => 'application/json'
            ])->timeout(15)->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => 'meta-llama/llama-4-maverick',
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
} 