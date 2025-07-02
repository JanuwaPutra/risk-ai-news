@extends('layouts.app')

@section('page_title', 'Analisis AI')

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
    
    .card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    
    .stats-card {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        margin-bottom: 1rem;
    }
    
    .results-table {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        padding: 1.25rem;
        margin-bottom: 1rem;
    }
    
    .progress {
        height: 0.7rem;
        border-radius: 0.375rem;
        overflow: hidden;
        margin-bottom: 0.75rem;
    }
    
    .card-header {
        border-bottom: none;
        padding: 0.8rem 1.2rem;
    }
    
    .card-body {
        padding: 1rem 1.2rem;
    }
    
    /* Link button styling */
    .btn-outline-primary {
        transition: all 0.2s ease;
        border-color: #4361ee;
        color: #4361ee;
    }
    
    .btn-outline-primary:hover {
        background-color: #4361ee;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 3px 5px rgba(0,0,0,0.1);
    }
    
    .btn-outline-primary i {
        font-size: 1.2rem;
    }
    
    /* Make sure the link button is centered in the table cell */
    td .btn {
        margin: 0 auto;
    }
    
    /* Responsive handling for the link button */
    @media (max-width: 768px) {
        .btn-outline-primary span {
            display: none;
        }
        
        .btn-outline-primary i {
            margin: 0;
        }
    }
</style>
@endsection

@section('content')
<div class="row mb-3">
    <div class="col-md-12">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <span class="d-inline-block bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                    <i class="bi bi-graph-up fs-3"></i>
                </span>
            </div>
            <div>
                <h2 class="mb-1">Analisis AI</h2>
                <p class="text-muted mb-0">Hasil analisis risiko dari konten media</p>
            </div>
        </div>
    </div>
</div>

<!-- Category Distribution Charts -->
<div class="row g-3 mb-3">
    <!-- Left Column: Kategori Risk Distribution -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-dark bg-gradient text-white rounded-top">
                <div class="d-flex align-items-center">
                    <i class="bi bi-bar-chart me-2"></i>
                    <h5 class="mb-0">Distribusi Kategori Risiko</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium">RENDAH</span>
                        <span class="fw-medium">{{ $stats['kategori_count']['RENDAH_pct'] }}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $stats['kategori_count']['RENDAH_pct'] }}%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium">SEDANG</span>
                        <span class="fw-medium">{{ $stats['kategori_count']['SEDANG_pct'] }}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $stats['kategori_count']['SEDANG_pct'] }}%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium">TINGGI</span>
                        <span class="fw-medium">{{ $stats['kategori_count']['TINGGI_pct'] }}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="background-color: #fb923c; width: {{ $stats['kategori_count']['TINGGI_pct'] }}%" role="progressbar"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium">KRITIS</span>
                        <span class="fw-medium">{{ $stats['kategori_count']['KRITIS_pct'] }}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $stats['kategori_count']['KRITIS_pct'] }}%"></div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-journals fs-4 text-muted me-2"></i>
                            <span class="fw-medium">Total Analisis: {{ $stats['kategori_count']['RENDAH'] + $stats['kategori_count']['SEDANG'] + $stats['kategori_count']['TINGGI'] + $stats['kategori_count']['KRITIS'] }}</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge badge-rendah">RENDAH: {{ $stats['kategori_count']['RENDAH'] }}</span>
                        <span class="badge badge-sedang">SEDANG: {{ $stats['kategori_count']['SEDANG'] }}</span>
                        <span class="badge badge-tinggi">TINGGI: {{ $stats['kategori_count']['TINGGI'] }}</span>
                        <span class="badge badge-kritis">KRITIS: {{ $stats['kategori_count']['KRITIS'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Urgency Distribution -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-dark bg-gradient text-white rounded-top">
                <div class="d-flex align-items-center">
                    <i class="bi bi-alarm me-2"></i>
                    <h5 class="mb-0">Distribusi Tingkat Urgensi</h5>
                </div>
            </div>
            <div class="card-body">
                @php
                    $totalUrgensi = $stats['urgensi_count']['MONITORING'] + $stats['urgensi_count']['PERHATIAN'] + $stats['urgensi_count']['SEGERA'] + $stats['urgensi_count']['DARURAT'];
                    $monitoringPct = $totalUrgensi > 0 ? ($stats['urgensi_count']['MONITORING'] / $totalUrgensi) * 100 : 0;
                    $perhatianPct = $totalUrgensi > 0 ? ($stats['urgensi_count']['PERHATIAN'] / $totalUrgensi) * 100 : 0;
                    $segeraPct = $totalUrgensi > 0 ? ($stats['urgensi_count']['SEGERA'] / $totalUrgensi) * 100 : 0;
                    $daruratPct = $totalUrgensi > 0 ? ($stats['urgensi_count']['DARURAT'] / $totalUrgensi) * 100 : 0;
                @endphp
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium">MONITORING</span>
                        <span class="fw-medium">{{ round($monitoringPct) }}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-info" role="progressbar" style="width: {{ $monitoringPct }}%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium">PERHATIAN</span>
                        <span class="fw-medium">{{ round($perhatianPct) }}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $perhatianPct }}%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium">SEGERA</span>
                        <span class="fw-medium">{{ round($segeraPct) }}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="background-color: #fb923c; width: {{ $segeraPct }}%" role="progressbar"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium">DARURAT</span>
                        <span class="fw-medium">{{ round($daruratPct) }}%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $daruratPct }}%"></div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-stopwatch fs-4 text-muted me-2"></i>
                            <span class="fw-medium">Tingkat Urgensi</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge urgency-monitoring">MONITORING: {{ $stats['urgensi_count']['MONITORING'] }}</span>
                        <span class="badge urgency-perhatian">PERHATIAN: {{ $stats['urgensi_count']['PERHATIAN'] }}</span>
                        <span class="badge urgency-segera">SEGERA: {{ $stats['urgensi_count']['SEGERA'] }}</span>
                        <span class="badge urgency-darurat">DARURAT: {{ $stats['urgensi_count']['DARURAT'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Results Table -->
<div class="results-table mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 d-flex align-items-center">
            <i class="bi bi-table me-2"></i>
            <span>Tabel Hasil Analisis</span>
        </h4>
    </div>
    
    <div class="mb-4">
        <h5 class="mb-3">Filter Cepat:</h5>
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
        <table class="table table-striped table-hover" id="analysisTable">
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
                    <th style="width: 120px;">Link Artikel</th>
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
                    <td class="text-center">
                        @if($result->url)
                            <a href="{{ $result->url }}" target="_blank" 
                               class="btn btn-sm btn-outline-primary d-flex align-items-center justify-content-center gap-1"
                               data-bs-toggle="tooltip" data-bs-placement="left" 
                               title="{{ $result->source ?? 'Buka artikel di tab baru' }}">
                                <i class="bi bi-link-45deg"></i>
                                <span>Buka</span>
                            </a>
                        @else
                            <span class="badge bg-secondary">Tidak ada</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Initialize DataTable
        var dataTable = $('#analysisTable').DataTable({
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
                { responsivePriority: 1, targets: [1, 6, 8, 10] }, // Prioritas kolom yang ditampilkan di responsive view
                { responsivePriority: 2, targets: [0, 3] },
                { width: "120px", targets: 10 } // Set width for link column
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
    });
</script>
@endsection 