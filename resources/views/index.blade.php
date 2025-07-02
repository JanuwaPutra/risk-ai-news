@extends('layouts.app')

@section('page_title', 'Upload & Analisis')

@section('additional_css')
<style>
    .dataTables_filter {
        margin-bottom: 1rem;
    }
    
    .dataTables_length select {
        min-width: 80px !important;
        width: auto !important;
        padding-right: 30px !important;
    }
    
    .table-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .table-filter-item {
        flex: 0 0 auto;
    }
    
    .filter-chip {
        border-radius: 20px;
        padding: 0.5rem 1rem;
        background: #f1f5f9;
        color: #475569;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
    }
    
    .filter-chip:hover, .filter-chip.active {
        background: #4361ee;
        color: white;
    }
    
    .filter-chip i {
        margin-right: 0.5rem;
    }
    
    .summary-text {
        position: relative;
        word-wrap: break-word;
        white-space: normal;
        min-width: 250px;
        max-width: 100%;
        font-size: var(--font-size-sm);
    }
    
    .ringkasan-lengkap {
        color: var(--primary-color);
        cursor: pointer;
        font-size: 0.8rem;
        text-decoration: underline;
        margin-top: 5px;
        display: inline-block;
    }
    
    .modal-body {
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    
    .upload-section {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        border-top: 4px solid var(--primary-color);
    }
    
    .progress-container {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        border-left: 4px solid var(--primary-color);
    }
    
    .filter-section {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }
    
    .results-table {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }
    
    .progress-log {
        border-radius: 8px;
        max-height: 200px;
        overflow-y: auto;
        background-color: #f8fafc;
        padding: 0.75rem;
        border: 1px solid #e2e8f0;
        font-family: monospace;
        font-size: 0.875rem;
        line-height: 1.5;
    }
    
    .progress-log p {
        margin-bottom: 0.4rem;
    }
    
    .progress-log p:last-child {
        margin-bottom: 0;
    }
</style>
@endsection

@section('content')
<div class="row mb-3">
    <div class="col-md-12">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <span class="d-inline-block bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                    <i class="bi bi-upload fs-3"></i>
                </span>
            </div>
            <div>
                <h2 class="mb-1">Upload & Analisis</h2>
                <p class="text-muted mb-0">Upload berita untuk dianalisis menggunakan AI</p>
            </div>
        </div>
    </div>
</div>

<!-- File Upload Section -->
<div class="upload-section">
    <h4 class="mb-3 d-flex align-items-center">
        <i class="bi bi-file-earmark-text me-2"></i>
        <span>Upload Dokumen</span>
    </h4>
    
    @if(isset($tokoh_count) && $tokoh_count == 0)
    <div class="alert alert-warning d-flex align-items-center mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
        <div>
            Data tokoh belum tersedia. 
            <a href="{{ route('tokoh.import.form') }}" class="alert-link">Import data tokoh terlebih dahulu</a> sebelum melakukan analisis.
        </div>
    </div>
    @endif
    
    <form method="POST" action="{{ route('index') }}" enctype="multipart/form-data" id="upload-form">
        @csrf
        <div class="row g-3">
            <div class="col-md-12">
                <label for="berita_file" class="form-label fw-medium">Data Berita (Word)</label>
                <div class="input-group mb-2">
                    <input type="file" class="form-control" id="berita_file" name="berita_file" accept=".docx,.doc" required>
                    <span class="input-group-text"><i class="bi bi-file-earmark-word"></i></span>
                </div>
                <div class="form-text text-muted">
                    <i class="bi bi-info-circle me-1"></i> Pilih file Word yang berisi berita untuk dianalisis
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-primary px-4 py-2" {{ isset($tokoh_count) && $tokoh_count == 0 ? 'disabled' : '' }}>
                <i class="bi bi-lightning-charge me-1"></i> Analisis Data
            </button>
        </div>
    </form>
</div>

<!-- Progress Section -->
<div class="progress-container" id="progress-section" style="display: none;">
    <h4 class="mb-3 d-flex align-items-center">
        <i class="bi bi-activity me-2"></i>
        <span>Status Analisis</span>
    </h4>
    <div class="progress-info d-flex justify-content-between mb-2">
        <div class="d-flex align-items-center">
            <i class="bi bi-cpu me-2"></i>
            <span id="progress-current">0</span>/<span id="progress-total">0</span> paragraf dianalisis
        </div>
        <div class="badge bg-primary px-3 py-2" id="progress-status">Idle</div>
    </div>
    <div class="progress mb-3" style="height: 10px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progress-bar" role="progressbar" style="width: 0%"></div>
    </div>
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-info-circle me-2 text-primary"></i>
        <span id="progress-message" class="fw-medium">Menunggu...</span>
    </div>
    <div class="progress-log" id="progress-log">
        <p class="mb-1"><i class="bi bi-arrow-right-circle me-2"></i> Siap untuk menganalisis data...</p>
    </div>
</div>

<!-- Filter and Results -->
@if($results->count() > 0)
<div class="filter-section mt-3">
    <h4 class="mb-3 d-flex align-items-center">
        <i class="bi bi-funnel me-2"></i>
        <span>Filter Hasil Analisis</span>
    </h4>
    <form method="POST" action="{{ route('index') }}" class="row g-3 mb-3" id="filter-form">
        @csrf
        <div class="col-md-9">
            <label for="kategori" class="form-label fw-medium">Kategori Risiko</label>
            <select name="kategori" id="kategori" class="form-select">
                <option value="all" {{ $selected_kategori == 'all' ? 'selected' : '' }}>Semua Kategori</option>
                <option value="RENDAH" {{ $selected_kategori == 'RENDAH' ? 'selected' : '' }}>RENDAH (0-30%)</option>
                <option value="SEDANG" {{ $selected_kategori == 'SEDANG' ? 'selected' : '' }}>SEDANG (31-60%)</option>
                <option value="TINGGI" {{ $selected_kategori == 'TINGGI' ? 'selected' : '' }}>TINGGI (61-85%)</option>
                <option value="KRITIS" {{ $selected_kategori == 'KRITIS' ? 'selected' : '' }}>KRITIS (86-100%)</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-filter me-1"></i> Terapkan Filter
            </button>
        </div>
    </form>
</div>

<div class="results-table">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 d-flex align-items-center">
            <i class="bi bi-table me-2"></i>
            <span>Hasil Analisis</span>
        </h4>
        <a href="{{ route('analysis') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-list"></i> Lihat Semua Hasil
        </a>
    </div>
    
    <div class="mb-3">
        <h5 class="mb-2">Filter Tabel:</h5>
        <div class="table-filters">
            <div class="table-filter-item">
                <div class="filter-chip" data-column="kategori" data-value="RENDAH">
                    <i class="bi bi-circle-fill text-success"></i> RENDAH
                </div>
            </div>
            <div class="table-filter-item">
                <div class="filter-chip" data-column="kategori" data-value="SEDANG">
                    <i class="bi bi-circle-fill text-warning"></i> SEDANG
                </div>
            </div>
            <div class="table-filter-item">
                <div class="filter-chip" data-column="kategori" data-value="TINGGI">
                    <i class="bi bi-circle-fill" style="color: #fb923c;"></i> TINGGI
                </div>
            </div>
            <div class="table-filter-item">
                <div class="filter-chip" data-column="kategori" data-value="KRITIS">
                    <i class="bi bi-circle-fill text-danger"></i> KRITIS
                </div>
            </div>
            <div class="table-filter-item">
                <div class="filter-chip" data-column="urgensi" data-value="MONITORING">
                    <i class="bi bi-circle-fill text-info"></i> MONITORING
                </div>
            </div>
            <div class="table-filter-item">
                <div class="filter-chip" data-column="urgensi" data-value="PERHATIAN">
                    <i class="bi bi-circle-fill text-warning"></i> PERHATIAN
                </div>
            </div>
            <div class="table-filter-item">
                <div class="filter-chip" data-column="urgensi" data-value="SEGERA">
                    <i class="bi bi-circle-fill" style="color: #fb923c;"></i> SEGERA
                </div>
            </div>
            <div class="table-filter-item">
                <div class="filter-chip" data-column="urgensi" data-value="DARURAT">
                    <i class="bi bi-circle-fill text-danger"></i> DARURAT
                </div>
            </div>
            <div class="table-filter-item ms-2">
                <div class="filter-chip reset-filter">
                    <i class="bi bi-x-circle"></i> Reset Filter
                </div>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover" id="resultsTable">
            <thead>
                <tr>
                    <th>Tanggal Ditambah</th>
                    <th>Nama Tokoh</th>
                    <th>Jabatan</th>
                    <th>Ringkasan</th>
                    <th>Skor</th>
                    <th>Kerawanan</th>
                    <th>Kategori</th>
                    <th>Faktor Risiko</th>
                    <th>Urgensi</th>
                    <th>Rekomendasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $result)
                <tr class="{{ $result->kategori == 'KRITIS' ? 'risk-critical' : ($result->kategori == 'TINGGI' ? 'risk-high' : ($result->kategori == 'SEDANG' ? 'risk-medium' : 'risk-low')) }}"
                    data-kategori="{{ $result->kategori }}" 
                    data-urgensi="{{ $result->urgensi }}">
                    <td><strong>{{ $result->tanggal_tambah ? $result->tanggal_tambah->format('Y-m-d H:i') : 'Data lama' }}</strong></td>
                    <td>{{ $result->nama }}</td>
                    <td>{{ $result->jabatan }}</td>
                    <td>
                        <div class="summary-text">{{ $result->ringkasan }}</div>
                    </td>
                    <td>{{ $result->skor_risiko }}</td>
                    <td>{{ $result->persentase_kerawanan }}</td>
                    <td>
                        <span class="badge {{ $result->kategori == 'RENDAH' ? 'badge-rendah' : ($result->kategori == 'SEDANG' ? 'badge-sedang' : ($result->kategori == 'TINGGI' ? 'badge-tinggi' : 'badge-kritis')) }}">
                            {{ $result->kategori }}
                        </span>
                    </td>
                    <td>{{ is_array($result->faktor_risiko) ? implode(', ', $result->faktor_risiko) : $result->faktor_risiko }}</td>
                    <td>
                        <span class="badge {{ $result->urgensi == 'MONITORING' ? 'urgency-monitoring' : ($result->urgensi == 'PERHATIAN' ? 'urgency-perhatian' : ($result->urgensi == 'SEGERA' ? 'urgency-segera' : 'urgency-darurat')) }}">
                            {{ $result->urgensi }}
                        </span>
                    </td>
                    <td>{{ $result->rekomendasi }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@section('scripts')
<script>
    // Event Source for Progress Updates
    document.addEventListener('DOMContentLoaded', function() {
        const uploadForm = document.getElementById('upload-form');
        const progressSection = document.getElementById('progress-section');
        const progressBar = document.getElementById('progress-bar');
        const progressCurrent = document.getElementById('progress-current');
        const progressTotal = document.getElementById('progress-total');
        const progressMessage = document.getElementById('progress-message');
        const progressLog = document.getElementById('progress-log');
        const progressStatus = document.getElementById('progress-status');
        
        uploadForm.addEventListener('submit', function() {
            progressSection.style.display = 'block';
            
            // Connect to SSE endpoint
            const eventSource = new EventSource('{{ route("progress") }}');
            
            // Listen for updates
            eventSource.addEventListener('message', function(event) {
                const data = JSON.parse(event.data);
                
                // Skip heartbeats
                if (data.heartbeat) return;
                
                // Update progress UI
                if (data.current !== undefined && data.total !== undefined) {
                    const percent = data.total > 0 ? Math.round((data.current / data.total) * 100) : 0;
                    progressBar.style.width = percent + '%';
                    progressBar.setAttribute('aria-valuenow', percent);
                    progressCurrent.textContent = data.current;
                    progressTotal.textContent = data.total;
                }
                
                // Update status message
                if (data.message) {
                    progressMessage.textContent = data.message;
                }
                
                // Update status class
                if (data.status) {
                    progressStatus.textContent = data.status;
                    progressStatus.className = 'badge bg-primary px-3 py-2';
                    
                    if (data.status.toLowerCase() === 'processing') {
                        progressStatus.classList.replace('bg-primary', 'bg-warning');
                    } else if (data.status.toLowerCase() === 'completed') {
                        progressStatus.classList.replace('bg-primary', 'bg-success');
                    } else if (data.status.toLowerCase() === 'error') {
                        progressStatus.classList.replace('bg-primary', 'bg-danger');
                    }
                }
                
                // Add to log if there's detail
                if (data.detail) {
                    const logEntry = document.createElement('p');
                    logEntry.className = 'mb-1';
                    logEntry.innerHTML = `<i class="bi bi-arrow-right-circle me-2"></i> ${data.detail}`;
                    progressLog.appendChild(logEntry);
                    progressLog.scrollTop = progressLog.scrollHeight;
                }
                
                // Check if complete
                if (data.complete) {
                    eventSource.close();
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                }
            });
            
            // Handle errors
            eventSource.addEventListener('error', function(e) {
                console.error('SSE Error:', e);
                const logEntry = document.createElement('p');
                logEntry.innerHTML = '<i class="bi bi-exclamation-triangle me-2 text-danger"></i> Connection error. Trying to reconnect...';
                logEntry.classList.add('text-danger', 'mb-1');
                progressLog.appendChild(logEntry);
            });
        });
        
        // Initialize DataTable if results table exists
        if (document.getElementById('resultsTable')) {
            var dataTable = $('#resultsTable').DataTable({
                responsive: true,
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Selanjutnya",
                        previous: "Sebelumnya"
                    },
                    zeroRecords: "Tidak ada data yang cocok"
                },
                columnDefs: [
                    { responsivePriority: 1, targets: [1, 6, 8] }, // Prioritas kolom yang ditampilkan di responsive view
                    { responsivePriority: 2, targets: [0, 3] }
                ]
            });
            
            // Custom filtering function for Kategori and Urgensi
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    var $row = $(dataTable.row(dataIndex).node());
                    var kategoriFilter = $('#kategoriFilter').val();
                    var urgensiFilter = $('#urgensiFilter').val();
                    
                    // If no filters are active, show all rows
                    if (!kategoriFilter && !urgensiFilter) {
                        return true;
                    }
                    
                    var rowKategori = $row.data('kategori');
                    var rowUrgensi = $row.data('urgensi');
                    
                    // Apply kategori filter if active
                    if (kategoriFilter && rowKategori !== kategoriFilter) {
                        return false;
                    }
                    
                    // Apply urgensi filter if active
                    if (urgensiFilter && rowUrgensi !== urgensiFilter) {
                        return false;
                    }
                    
                    return true;
                }
            );
            
            // Handle filter chip clicks
            $('.filter-chip').click(function() {
                if ($(this).hasClass('reset-filter')) {
                    // Reset all filters
                    $('.filter-chip').removeClass('active');
                    $('#kategoriFilter, #urgensiFilter').val('');
                } else {
                    var column = $(this).data('column');
                    var value = $(this).data('value');
                    
                    // Toggle this filter
                    $(this).toggleClass('active');
                    
                    // If this filter was just activated
                    if ($(this).hasClass('active')) {
                        // Deactivate other filters in the same column
                        $('.filter-chip.active').not(this).each(function() {
                            if ($(this).data('column') === column) {
                                $(this).removeClass('active');
                            }
                        });
                        
                        // Set the filter value
                        if (column === 'kategori') {
                            $('#kategoriFilter').val(value);
                        } else if (column === 'urgensi') {
                            $('#urgensiFilter').val(value);
                        }
                    } else {
                        // Clear this filter value
                        if (column === 'kategori') {
                            $('#kategoriFilter').val('');
                        } else if (column === 'urgensi') {
                            $('#urgensiFilter').val('');
                        }
                    }
                }
                
                // Redraw the table with the new filters
                dataTable.draw();
            });
            
            // Add hidden fields for tracking active filters
            $('body').append('<input type="hidden" id="kategoriFilter" value="">');
            $('body').append('<input type="hidden" id="urgensiFilter" value="">');
        }
    });
</script>
@endsection
