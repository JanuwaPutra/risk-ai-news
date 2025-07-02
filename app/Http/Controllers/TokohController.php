<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tokoh;
use App\Services\DocumentParserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TokohController extends Controller
{
    protected $documentParser;
    
    /**
     * Create a new controller instance.
     *
     * @param DocumentParserService $documentParser
     */
    public function __construct(DocumentParserService $documentParser)
    {
        $this->documentParser = $documentParser;
    }
    
    /**
     * Display a listing of all tokoh data.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $tokohData = Tokoh::orderBy('nama')->get();
        
        return view('tokoh.index', [
            'tokohData' => $tokohData
        ]);
    }
    
    /**
     * Show the form for importing tokoh data.
     *
     * @return \Illuminate\View\View
     */
    public function importForm()
    {
        return view('tokoh.import');
    }
    
    /**
     * Import tokoh data from Excel file.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function import(Request $request)
    {
        $request->validate([
            'tokoh_file' => 'required|file|mimes:xlsx,xls,csv'
        ]);
        
        try {
            $file = $request->file('tokoh_file');
            $filePath = $file->store('uploads');
            $fullPath = Storage::path($filePath);
            
            // Parse Excel data
            $tokohData = $this->documentParser->readExcelData($fullPath);
            
            // Count for stats
            $importedCount = 0;
            $updatedCount = 0;
            $errorCount = 0;
            
            // Process each row
            foreach ($tokohData as $data) {
                try {
                    // Skip if no name is provided
                    if (empty($data['nama'])) {
                        continue;
                    }
                    
                    // Try to find existing record
                    $tokoh = Tokoh::where('nama', $data['nama'])->first();
                    
                    if ($tokoh) {
                        // Update existing record
                        $tokoh->update([
                            'jenis_kelamin' => $data['jenis_kelamin'] ?? $tokoh->jenis_kelamin,
                            'kta' => $data['kta'] ?? $tokoh->kta,
                            'jabatan' => $data['jabatan'] ?? $tokoh->jabatan,
                            'tingkat' => $data['tingkat'] ?? $tokoh->tingkat,
                        ]);
                        $updatedCount++;
                    } else {
                        // Create new record
                        Tokoh::create([
                            'nama' => $data['nama'],
                            'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                            'kta' => $data['kta'] ?? null,
                            'jabatan' => $data['jabatan'] ?? null,
                            'tingkat' => $data['tingkat'] ?? null,
                        ]);
                        $importedCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error("Error importing tokoh data: {$e->getMessage()}", [
                        'data' => $data
                    ]);
                }
            }
            
            // Delete the temporary file
            Storage::delete($filePath);
            
            // Return with success message
            return redirect()->route('tokoh.index')->with('success', 
                "Import successful. {$importedCount} records imported, {$updatedCount} records updated, {$errorCount} errors encountered.");
            
        } catch (\Exception $e) {
            Log::error("Error importing tokoh file: {$e->getMessage()}");
            return redirect()->route('tokoh.import')->with('error', 
                'Error importing file: ' . $e->getMessage());
        }
    }
    
    /**
     * Update a single field for a tokoh record via AJAX
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateField(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|integer',
                'field' => 'required|string',
                'value' => 'nullable|string'
            ]);
            
            $id = $request->input('id');
            $field = $request->input('field');
            $value = $request->input('value');
            
            // Only allow updating specific fields
            $allowedFields = ['nama', 'alias', 'jabatan', 'jenis_kelamin', 'kta', 'tingkat'];
            if (!in_array($field, $allowedFields)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Field cannot be updated'
                ], 403);
            }
            
            // For name field, ensure it's not empty
            if ($field === 'nama' && empty($value)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Name cannot be empty'
                ], 400);
            }
            
            $tokoh = Tokoh::findOrFail($id);
            $tokoh->$field = $value;
            $tokoh->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Field updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating tokoh field: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Remove all tokoh data from the database.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteAll()
    {
        $count = Tokoh::count();
        Tokoh::truncate();
        
        return redirect()->route('tokoh.index')->with('success', 
            "{$count} tokoh records have been deleted.");
    }
}
