@extends('layouts.app')

@section('page_title', 'Analisis Berita Otomatis')

@section('additional_css')
<style>
    .person-select {
        max-height: 250px;
        overflow-y: auto;
    }
    
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        flex-direction: column;
    }
    
    .article-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .article-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .result-table {
        font-size: 0.85rem;
    }
    
    .result-badge {
        font-size: 0.7rem;
        padding: 0.35rem 0.6rem;
    }
    
    .task-status {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        width: 320px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .pulse {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
        animation: pulse 2s infinite;
        margin-right: 5px;
    }
    
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
        }
    }
    
    .pulse-paused {
        background: #f59e0b;
        box-shadow: 0 0 0 rgba(245, 158, 11, 0.4);
        animation: pulse-paused 2s infinite;
    }
    
    @keyframes pulse-paused {
        0% {
            box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(245, 158, 11, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
        }
    }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12 mb-4">
        <h2><i class="bi bi-robot"></i> Analisis Berita Otomatis</h2>
        <p class="text-muted">Cari dan analisis berita secara otomatis tanpa perlu klik tombol pencarian</p>
    </div>
</div>

<!-- Search Form -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-search"></i> Cari & Analisis Berita</h5>
    </div>
    <div class="card-body">
        <form id="autoAnalysisForm">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="query" class="form-label">Kata Kunci</label>
                    <input type="text" class="form-control" id="query" name="query" required placeholder="Masukkan kata kunci pencarian..." value="{{ $query }}">
                    <div class="form-text">Cari berita berbahasa Indonesia</div>
                </div>
                <div class="col-md-3">
                    <label for="from_date" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $fromDate }}">
                </div>
                <div class="col-md-3">
                    <label for="to_date" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $toDate }}">
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="include_today" name="include_today" value="1" {{ $includeToday ? 'checked' : '' }}>
                        <label class="form-check-label" for="include_today">
                            Termasuk hari ini
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <label class="form-label">Pilih Tokoh untuk Dianalisis (opsional)</label>
                    <div class="border rounded p-3 person-select">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="select-all" value="">
                            <label class="form-check-label fw-bold" for="select-all">
                                Pilih Semua Tokoh
                            </label>
                        </div>
                        <hr>
                        @if($tokohData->count() > 0)
                            <div class="row">
                                @foreach($tokohData as $tokoh)
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input person-checkbox" type="checkbox" name="person_ids[]" id="person-{{ $tokoh->id }}" value="{{ $tokoh->id }}" {{ in_array($tokoh->id, $personIds) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="person-{{ $tokoh->id }}">
                                                {{ $tokoh->nama }} ({{ $tokoh->jabatan ?? 'Tidak ada jabatan' }})
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle"></i> Tidak ada data tokoh. 
                                <a href="{{ route('tokoh.import.form') }}" class="alert-link">Import data tokoh</a> terlebih dahulu.
                            </div>
                        @endif
                    </div>
                    <div class="form-text">Jika tidak ada tokoh yang dipilih, semua tokoh akan dianalisis</div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" id="analysis-btn">
                        <i class="bi bi-search"></i> Cari & Analisis Otomatis
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Results Section -->
<div id="results-container" style="display: none;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-newspaper"></i> Hasil Analisis</h4>
        <a href="{{ route('analysis') }}" class="btn btn-outline-primary">
            <i class="bi bi-table"></i> Lihat Semua Hasil Analisis
        </a>
    </div>
    
    <div class="alert alert-info" id="results-summary">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Informasi Analisis</h5>
        <p id="analysis-summary-text">Sedang memuat informasi...</p>
    </div>
    
    <!-- Table Results -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-table"></i> Ringkasan Hasil Analisis</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover result-table" id="results-table">
                    <thead>
                        <tr>
                            <th>Nama Tokoh</th>
                            <th>Jabatan</th>
                            <th>Skor Risiko</th>
                            <th>Kategori</th>
                            <th>Sumber</th>
                            <th>Berita</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="results-table-body">
                        <!-- Results will be inserted here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Articles Results -->
    <h4 class="mb-3"><i class="bi bi-newspaper"></i> Artikel yang Dianalisis</h4>
    
    <div class="row g-4" id="articles-container">
        <!-- Articles will be inserted here -->
    </div>
</div>

<!-- No Results Message -->
<div class="alert alert-warning" id="no-results" style="display: none;">
    <i class="bi bi-exclamation-triangle"></i> Tidak ditemukan hasil yang cocok. Coba kata kunci lain atau ubah rentang tanggal.
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loading-overlay" style="display: none;">
    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
    </div>
    <h4 class="mt-3">Sedang Menganalisis Berita...</h4>
    <p class="text-muted">Harap tunggu. Proses ini mungkin memerlukan waktu beberapa saat.</p>
    <div class="progress mt-3" style="width: 50%; min-width: 300px;">
        <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
    </div>
    <p class="mt-2" id="progress-text">Mempersiapkan analisis...</p>
</div>

<!-- Background Task Status -->
<div class="card task-status shadow" id="task-status-panel" style="display: none;">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
        <div>
            <span class="pulse" id="task-pulse"></span>
            <span id="task-status-title">Analisis Berjalan</span>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-light task-control-btn" data-action="pause" id="pause-btn">
                <i class="bi bi-pause-fill"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light task-control-btn" data-action="resume" id="resume-btn" style="display: none;">
                <i class="bi bi-play-fill"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light task-control-btn" data-action="stop">
                <i class="bi bi-stop-fill"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light" id="minimize-task-btn">
                <i class="bi bi-dash"></i>
            </button>
        </div>
    </div>
    <div class="card-body py-2" id="task-status-body">
        <p class="mb-1"><strong>Pencarian:</strong> <span id="task-query"></span></p>
        <p class="mb-1"><strong>Status:</strong> <span id="task-status"></span></p>
        <p class="mb-1"><strong>Hasil:</strong> <span id="task-results-count">0</span> analisis ditemukan</p>
        <p class="mb-0"><strong>Terakhir dijalankan:</strong> <span id="task-last-run">Baru saja</span></p>
        
        <div class="mt-2 d-flex justify-content-between">
            <a href="#" class="btn btn-sm btn-outline-primary" id="view-all-results-btn">
                <i class="bi bi-eye"></i> Lihat Semua Hasil
            </a>
            <button class="btn btn-sm btn-outline-secondary" id="refresh-status-btn">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>
</div>

<!-- Minimized Task Indicator -->
<div class="position-fixed bottom-0 end-0 m-3" id="minimized-task-indicator" style="display: none; z-index: 1000;">
    <button class="btn btn-primary rounded-circle p-2" id="expand-task-btn" style="width: 50px; height: 50px;">
        <i class="bi bi-graph-up"></i>
    </button>
</div>

<!-- Debugging Panel (only visible in development) -->
@if(config('app.env') === 'local' || config('app.debug'))
<div class="card mt-5">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-bug"></i> Debug Information</h5>
        <button class="btn btn-sm btn-light" id="save-debug-btn">
            <i class="bi bi-download"></i> Save Debug Info
        </button>
    </div>
    <div class="card-body">
        <h6>Session Data:</h6>
        <pre class="bg-light p-3">Query: {{ $query }}
From Date: {{ $fromDate }}
To Date: {{ $toDate }}
Include Today: {{ $includeToday ? 'Yes' : 'No' }}
Selected People: {{ implode(', ', $personIds) }}</pre>
        
        <h6 class="mt-3">Request Information:</h6>
        <pre class="bg-light p-3">Route: {{ request()->route()->getName() }}
URL: {{ request()->url() }}
Previous URL: {{ url()->previous() }}</pre>
        
        <h6 class="mt-3">JavaScript Debug:</h6>
        <div id="js-debug" class="bg-light p-3">
            <p>Waiting for JS debug info...</p>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Debug logging function
        function debugLog(message, data = null) {
            console.log(message, data);
            const jsDebug = document.getElementById('js-debug');
            if (jsDebug) {
                const timestamp = new Date().toLocaleTimeString();
                const logEntry = document.createElement('div');
                logEntry.innerHTML = `<strong>[${timestamp}]</strong> ${message}`;
                if (data) {
                    const dataJson = JSON.stringify(data, null, 2);
                    logEntry.innerHTML += `<pre>${dataJson}</pre>`;
                }
                jsDebug.appendChild(logEntry);
            }
        }
        
        // Initial debug log
        debugLog('Page loaded with stored parameters');
        
        // Save debug information
        const saveDebugBtn = document.getElementById('save-debug-btn');
        if (saveDebugBtn) {
            saveDebugBtn.addEventListener('click', function() {
                const jsDebug = document.getElementById('js-debug');
                const sessionData = document.querySelector('pre').textContent;
                const requestInfo = document.querySelectorAll('pre')[1].textContent;
                
                let debugContent = 'DEBUG INFORMATION\n';
                debugContent += '=================\n\n';
                debugContent += 'Time: ' + new Date().toLocaleString() + '\n\n';
                debugContent += 'SESSION DATA:\n';
                debugContent += sessionData + '\n\n';
                debugContent += 'REQUEST INFORMATION:\n';
                debugContent += requestInfo + '\n\n';
                debugContent += 'JAVASCRIPT DEBUG LOG:\n';
                debugContent += jsDebug.textContent;
                
                // Create blob and download
                const blob = new Blob([debugContent], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'debug-info-' + new Date().toISOString().replace(/[:.]/g, '-') + '.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                debugLog('Debug information saved');
            });
        }
        
        // Background task variables
        let currentTaskId = null;
        let taskCheckInterval = null;
        let taskStatus = 'active';
        
        // Background task status panel control
        const taskStatusPanel = document.getElementById('task-status-panel');
        const minimizedTaskIndicator = document.getElementById('minimized-task-indicator');
        const minimizeTaskBtn = document.getElementById('minimize-task-btn');
        const expandTaskBtn = document.getElementById('expand-task-btn');
        
        if (minimizeTaskBtn) {
            minimizeTaskBtn.addEventListener('click', function() {
                if (taskStatusPanel) taskStatusPanel.style.display = 'none';
                if (minimizedTaskIndicator) minimizedTaskIndicator.style.display = 'block';
                debugLog('Task panel minimized');
            });
        }
        
        if (expandTaskBtn) {
            expandTaskBtn.addEventListener('click', function() {
                if (taskStatusPanel) taskStatusPanel.style.display = 'block';
                if (minimizedTaskIndicator) minimizedTaskIndicator.style.display = 'none';
                debugLog('Task panel expanded');
            });
        }
        
        // Task control buttons
        const taskControlBtns = document.querySelectorAll('.task-control-btn');
        taskControlBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                
                if (!currentTaskId) {
                    debugLog('No active task to control');
                    return;
                }
                
                // Control task
                controlTask(currentTaskId, action);
            });
        });
        
        // Refresh status button
        const refreshStatusBtn = document.getElementById('refresh-status-btn');
        if (refreshStatusBtn) {
            refreshStatusBtn.addEventListener('click', function() {
                if (currentTaskId) {
                    checkTaskStatus(currentTaskId);
                    debugLog('Manually refreshed task status');
                }
            });
        }
        
        // View all results button
        const viewAllResultsBtn = document.getElementById('view-all-results-btn');
        if (viewAllResultsBtn) {
            viewAllResultsBtn.addEventListener('click', function() {
                window.location.href = '{{ route("analysis") }}';
            });
        }
        
        // Function to control task
        function controlTask(taskId, action) {
            fetch('{{ route("news.task.control") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    task_id: taskId,
                    action: action
                })
            })
            .then(response => response.json())
            .then(data => {
                debugLog('Task control response', data);
                
                if (data.success) {
                    // Update task status UI
                    updateTaskStatusUI(data.task.status);
                    
                    // If task is completed/stopped, clear interval
                    if (data.task.status === 'completed') {
                        clearInterval(taskCheckInterval);
                        currentTaskId = null;
                    }
                } else {
                    alert('Gagal mengontrol tugas: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                debugLog('Error controlling task', error.message);
            });
        }
        
        // Function to update task status UI
        function updateTaskStatusUI(status) {
            const taskPulse = document.getElementById('task-pulse');
            const taskStatus = document.getElementById('task-status');
            const taskStatusTitle = document.getElementById('task-status-title');
            const pauseBtn = document.getElementById('pause-btn');
            const resumeBtn = document.getElementById('resume-btn');
            
            if (taskStatus) taskStatus.textContent = status;
            
            switch (status) {
                case 'active':
                    if (taskPulse) {
                        taskPulse.classList.remove('pulse-paused');
                        taskPulse.classList.add('pulse');
                    }
                    if (taskStatusTitle) taskStatusTitle.textContent = 'Analisis Berjalan';
                    if (pauseBtn) pauseBtn.style.display = 'inline-block';
                    if (resumeBtn) resumeBtn.style.display = 'none';
                    break;
                    
                case 'paused':
                    if (taskPulse) {
                        taskPulse.classList.remove('pulse');
                        taskPulse.classList.add('pulse-paused');
                    }
                    if (taskStatusTitle) taskStatusTitle.textContent = 'Analisis Dijeda';
                    if (pauseBtn) pauseBtn.style.display = 'none';
                    if (resumeBtn) resumeBtn.style.display = 'inline-block';
                    break;
                    
                case 'completed':
                    if (taskPulse) {
                        taskPulse.classList.remove('pulse');
                        taskPulse.classList.remove('pulse-paused');
                        taskPulse.style.background = '#9ca3af';
                    }
                    if (taskStatusTitle) taskStatusTitle.textContent = 'Analisis Selesai';
                    if (pauseBtn) pauseBtn.style.display = 'none';
                    if (resumeBtn) resumeBtn.style.display = 'none';
                    break;
                    
                case 'failed':
                    if (taskPulse) {
                        taskPulse.classList.remove('pulse');
                        taskPulse.classList.remove('pulse-paused');
                        taskPulse.style.background = '#ef4444';
                    }
                    if (taskStatusTitle) taskStatusTitle.textContent = 'Analisis Gagal';
                    if (pauseBtn) pauseBtn.style.display = 'none';
                    if (resumeBtn) resumeBtn.style.display = 'inline-block';
                    break;
            }
        }
        
        // Function to check task status
        function checkTaskStatus(taskId) {
            fetch('{{ route("news.task.status") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    task_id: taskId
                })
            })
            .then(response => response.json())
            .then(data => {
                debugLog('Task status check', data);
                
                if (data.success) {
                    // Update task status panel
                    const taskQuery = document.getElementById('task-query');
                    const taskStatus = document.getElementById('task-status');
                    const taskResultsCount = document.getElementById('task-results-count');
                    const taskLastRun = document.getElementById('task-last-run');
                    
                    if (taskQuery) taskQuery.textContent = data.task.query;
                    if (taskStatus) taskStatus.textContent = data.task.status;
                    if (taskResultsCount) taskResultsCount.textContent = data.task.results_count;
                    if (taskLastRun) taskLastRun.textContent = data.task.last_run_at || 'Baru saja';
                    
                    // Update UI based on status
                    updateTaskStatusUI(data.task.status);
                    
                    // If task is not active anymore, clear interval
                    if (!data.is_active) {
                        clearInterval(taskCheckInterval);
                    }
                } else {
                    debugLog('Error checking task status', data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                debugLog('Error checking task status', error.message);
            });
        }
        
        // Function to start task status polling
        function startTaskPolling(taskId) {
            // Clear any existing interval
            if (taskCheckInterval) {
                clearInterval(taskCheckInterval);
            }
            
            // Set current task ID
            currentTaskId = taskId;
            
            // Show task status panel
            if (taskStatusPanel) taskStatusPanel.style.display = 'block';
            
            // Check status immediately
            checkTaskStatus(taskId);
            
            // Set interval for checking task status every 30 seconds
            taskCheckInterval = setInterval(() => {
                checkTaskStatus(taskId);
            }, 30000); // 30 seconds
            
            debugLog('Started task polling for task ID', taskId);
        }
        
        // Select all checkbox
        const selectAllCheckbox = document.getElementById('select-all');
        const personCheckboxes = document.querySelectorAll('.person-checkbox');
        
        // Check if "select all" should be checked based on existing selections
        if (selectAllCheckbox && personCheckboxes.length > 0) {
            const allChecked = Array.from(personCheckboxes).every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            debugLog('Initial select-all state', { checked: allChecked });
        }
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                personCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
                debugLog('Select all changed', { checked: isChecked });
            });
        }
        
        // Individual checkboxes
        personCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // Check if all individual checkboxes are checked
                const allChecked = Array.from(personCheckboxes).every(cb => cb.checked);
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = allChecked;
                }
                debugLog('Checkbox changed', { 
                    id: this.id, 
                    checked: this.checked, 
                    allChecked: allChecked 
                });
            });
        });
        
        // Auto analysis form
        const autoAnalysisForm = document.getElementById('autoAnalysisForm');
        if (autoAnalysisForm) {
            autoAnalysisForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show loading overlay
                const loadingOverlay = document.getElementById('loading-overlay');
                if (loadingOverlay) loadingOverlay.style.display = 'flex';
                
                // Hide results
                const resultsContainer = document.getElementById('results-container');
                const noResults = document.getElementById('no-results');
                if (resultsContainer) resultsContainer.style.display = 'none';
                if (noResults) noResults.style.display = 'none';
                
                // Get form data
                const formData = new FormData(this);
                const data = {};
                
                formData.forEach((value, key) => {
                    if (key === 'person_ids[]') {
                        if (!data.person_ids) {
                            data.person_ids = [];
                        }
                        data.person_ids.push(value);
                    } else {
                        data[key] = value;
                    }
                });
                
                debugLog('Form submitted', data);
                
                // Update progress
                const progressBar = document.getElementById('progress-bar');
                const progressText = document.getElementById('progress-text');
                
                if (progressBar) progressBar.style.width = '20%';
                if (progressText) progressText.textContent = 'Mencari berita...';
                
                // Send analysis request
                fetch('{{ route("news.auto.process") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(data => {
                    // Update progress
                    if (progressBar) progressBar.style.width = '70%';
                    if (progressText) progressText.textContent = 'Memproses hasil...';
                    
                    debugLog('API response received', data);
                    
                    if (data.error) {
                        // Show error
                        alert(data.error);
                        debugLog('Error from API', data.error);
                    } else if (data.success) {
                        // If we have a task ID and background_running is true, start polling
                        if (data.task_id && data.background_running) {
                            startTaskPolling(data.task_id);
                        }
                        
                        // Process results
                        processResults(data);
                        
                        // Show results
                        if (resultsContainer) resultsContainer.style.display = 'block';
                    } else {
                        // Show no results message
                        if (noResults) noResults.style.display = 'block';
                        debugLog('No results found', data);
                    }
                    
                    // Update progress
                    if (progressBar) progressBar.style.width = '100%';
                    if (progressText) progressText.textContent = 'Selesai!';
                    
                    // Hide loading overlay after a short delay
                    setTimeout(() => {
                        if (loadingOverlay) loadingOverlay.style.display = 'none';
                    }, 500);
                })
                .catch(error => {
                    console.error('Error:', error);
                    debugLog('Fetch error', error.message);
                    alert('Terjadi kesalahan saat memproses permintaan: ' + error.message);
                    
                    // Hide loading overlay
                    if (loadingOverlay) loadingOverlay.style.display = 'none';
                });
            });
        }
        
        // Function to process and display results
        function processResults(data) {
            // Update summary
            const summaryText = document.getElementById('analysis-summary-text');
            if (summaryText) {
                summaryText.innerHTML = `
                    <strong>${data.totalProcessed}</strong> artikel berhasil dianalisis untuk kata kunci "<strong>${document.getElementById('query').value}</strong>".
                    <br>Total <strong>${data.results.length}</strong> hasil analisis tokoh ditemukan.
                `;
            }
            
            // Update results table
            const resultsTableBody = document.getElementById('results-table-body');
            if (resultsTableBody) {
                resultsTableBody.innerHTML = '';
                
                data.results.forEach(result => {
                    const row = document.createElement('tr');
                    
                    // Set row class based on risk category
                    if (result.kategori === 'KRITIS') {
                        row.classList.add('table-danger');
                    } else if (result.kategori === 'TINGGI') {
                        row.classList.add('table-warning');
                    } else if (result.kategori === 'SEDANG') {
                        row.classList.add('table-info');
                    } else {
                        row.classList.add('table-success');
                    }
                    
                    row.innerHTML = `
                        <td>${result.nama}</td>
                        <td>${result.jabatan || 'Tidak ada jabatan'}</td>
                        <td>${result.skor_risiko}</td>
                        <td>
                            <span class="badge rounded-pill bg-${
                                result.kategori === 'KRITIS' ? 'danger' : 
                                (result.kategori === 'TINGGI' ? 'warning' : 
                                (result.kategori === 'SEDANG' ? 'info' : 'success'))
                            } result-badge">${result.kategori}</span>
                        </td>
                        <td>${result.source}</td>
                        <td><small class="text-truncate d-inline-block" style="max-width: 200px;">${result.title}</small></td>
                        <td>
                            <a href="{{ route('analysis') }}?search=${encodeURIComponent(result.nama)}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    `;
                    
                    resultsTableBody.appendChild(row);
                });
            }
            
            // Update articles
            const articlesContainer = document.getElementById('articles-container');
            if (articlesContainer) {
                articlesContainer.innerHTML = '';
                
                data.articles.forEach(article => {
                    const card = document.createElement('div');
                    card.className = 'col-md-6 col-lg-4';
                    
                    // Calculate the highest risk
                    let highestRisk = { category: 'RENDAH', score: 0 };
                    article.results.forEach(result => {
                        if (
                            (result.category === 'KRITIS') || 
                            (result.category === 'TINGGI' && highestRisk.category !== 'KRITIS') ||
                            (result.category === 'SEDANG' && highestRisk.category !== 'KRITIS' && highestRisk.category !== 'TINGGI') ||
                            (result.category === 'RENDAH' && highestRisk.category === 'RENDAH' && result.score > highestRisk.score)
                        ) {
                            highestRisk = result;
                        }
                    });
                    
                    const riskBadgeColor = 
                        highestRisk.category === 'KRITIS' ? 'danger' : 
                        (highestRisk.category === 'TINGGI' ? 'warning' : 
                        (highestRisk.category === 'SEDANG' ? 'info' : 'success'));
                    
                    card.innerHTML = `
                        <div class="card article-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-primary">${article.source}</span>
                                    <small class="text-muted">${new Date(article.publishedAt).toLocaleDateString('id-ID')}</small>
                                </div>
                                <h5 class="card-title">${article.title}</h5>
                                
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <a href="${article.url}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-box-arrow-up-right"></i> Buka Artikel
                                        </a>
                                        <span class="badge bg-${riskBadgeColor} ms-2">${highestRisk.category} (${highestRisk.score})</span>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <p class="mb-2 fw-bold">Tokoh yang dianalisis:</p>
                                    <ul class="list-group list-group-flush">
                                        ${article.results.map(result => `
                                            <li class="list-group-item px-0 py-1 border-0">
                                                ${result.name} 
                                                <span class="badge bg-${
                                                    result.category === 'KRITIS' ? 'danger' : 
                                                    (result.category === 'TINGGI' ? 'warning' : 
                                                    (result.category === 'SEDANG' ? 'info' : 'success'))
                                                } float-end result-badge">${result.category} (${result.score})</span>
                                            </li>
                                        `).join('')}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    articlesContainer.appendChild(card);
                });
                
                // Show no results message if no articles
                if (data.articles.length === 0) {
                    const noResults = document.getElementById('no-results');
                    if (noResults) noResults.style.display = 'block';
                    
                    const resultsContainer = document.getElementById('results-container');
                    if (resultsContainer) resultsContainer.style.display = 'none';
                }
            }
            
            // Initialize DataTable for results
            if ($.fn.DataTable.isDataTable('#results-table')) {
                $('#results-table').DataTable().destroy();
            }
            
            $('#results-table').DataTable({
                responsive: true,
                order: [[2, 'desc']], // Sort by risk score descending
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                }
            });
        }
    });
</script>
@endsection
