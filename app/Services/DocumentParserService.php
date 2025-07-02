<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

class DocumentParserService
{
    /**
     * Read data from Excel file
     *
     * @param string|null $filePath
     * @return array
     */
    public function readExcelData(?string $filePath = null): array
    {
        if (!$filePath || !file_exists($filePath)) {
            return [];
        }
        
        try {
            $spreadsheet = SpreadsheetIOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Get header row for column names
            $header = array_shift($rows);
            
            // Convert to associative array
            $data = [];
            foreach ($rows as $row) {
                // Skip empty rows (specifically those with no name)
                if (empty($row[array_search('nama', $header, true)])) {
                    continue;
                }
                
                $item = [];
                foreach ($header as $index => $column) {
                    if (isset($row[$index])) {
                        $item[$column] = $row[$index];
                    }
                }
                
                // Ensure nama is treated as string
                if (isset($item['nama'])) {
                    $item['nama'] = (string)$item['nama'];
                }
                
                $data[] = $item;
            }
            
            return $data;
        } catch (\Exception $e) {
            \Log::error("Error reading Excel file: {$e->getMessage()}");
            return [];
        }
    }
    
    /**
     * Read paragraphs from Word file
     *
     * @param string|null $filePath
     * @return array
     */
    public function readWordParagraphs(?string $filePath = null): array
    {
        if (!$filePath || !file_exists($filePath)) {
            return [];
        }
        
        try {
            $phpWord = WordIOFactory::load($filePath);
            $paragraphs = [];
            
            // Loop through sections and get all paragraphs
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text = $element->getText();
                        if (!empty(trim($text))) {
                            $paragraphs[] = $text;
                        }
                    }
                }
            }
            
            return $paragraphs;
        } catch (\Exception $e) {
            \Log::error("Error reading Word file: {$e->getMessage()}");
            return [];
        }
    }
    
    /**
     * Match person to paragraph using fuzzy matching
     *
     * @param array $person
     * @param string $paragraph
     * @return bool
     */
    public function matchPersonToParagraph(array $person, string $paragraph): bool
    {
        // Get person name
        $name = $person['nama'] ?? '';
        
        // Skip empty names
        if (empty($name)) {
            return false;
        }
        
        // Convert name to string in case it's numeric
        $name = (string)$name;
        
        // Convert both to lowercase for better matching
        $nameLower = strtolower($name);
        $paragraphLower = strtolower($paragraph);
        
        // Direct substring match - only match exact names
        if (str_contains($paragraphLower, $nameLower)) {
            // Make sure it's a standalone name, not part of another word
            foreach (explode(' ', $paragraphLower) as $word) {
                if ($nameLower === $word) {
                    return true;
                }
            }
            
            // If name consists of multiple words, check if it appears as a phrase
            if (count(explode(' ', $nameLower)) > 1 && str_contains($paragraphLower, $nameLower)) {
                return true;
            }
        }
        
        // For multi-word names, try using more sophisticated matching
        if (count(explode(' ', $nameLower)) >= 2) {
            $words = explode(' ', $paragraphLower);
            for ($i = 0; $i < count($words); $i++) {
                for ($j = $i + 1; $j < min($i + 6, count($words) + 1); $j++) {
                    $phrase = implode(' ', array_slice($words, $i, $j - $i));
                    $similarity = $this->calculateSimilarity($nameLower, $phrase);
                    if ($similarity > 85) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Calculate similarity between two strings (simple implementation)
     *
     * @param string $str1
     * @param string $str2
     * @return float
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Levenshtein distance-based similarity
        $levDistance = levenshtein($str1, $str2);
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) return 0;
        
        // Convert to percentage similarity
        return (1 - $levDistance / $maxLen) * 100;
    }
} 