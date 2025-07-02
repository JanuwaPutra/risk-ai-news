@extends('layouts.app')

@section('page_title', 'Import Data Tokoh')

@section('additional_css')
<style>
    .card {
        margin-bottom: 2rem;
    }
    
    .card-body {
        padding: 1.75rem;
    }
    
    .import-steps {
        counter-reset: step-counter;
        list-style-type: none;
        padding-left: 0;
    }
    
    .import-steps li {
        counter-increment: step-counter;
        position: relative;
        padding-left: 2.5rem;
        margin-bottom: 1rem;
        line-height: 1.5;
    }
    
    .import-steps li::before {
        content: counter(step-counter);
        position: absolute;
        left: 0;
        top: 0;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 50%;
        background-color: rgba(67, 97, 238, 0.15);
        color: #4361ee;
        font-weight: 600;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 0.9rem;
    }
</style>
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <span class="d-inline-block bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                    <i class="bi bi-upload fs-3"></i>
                </span>
            </div>
            <div>
                <h2 class="mb-1">Import Data Tokoh</h2>
                <p class="text-muted mb-0">Upload file Excel dengan data tokoh</p>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Import Data Tokoh</h5>
                <a href="{{ route('tokoh.index') }}" class="btn btn-sm btn-light">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>
            
            <div class="card-body">
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                
                <form action="{{ route('tokoh.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3">Petunjuk Import:</h6>
                        <ol class="import-steps">
                            <li class="mb-2">File yang didukung: Excel (.xlsx, .xls) dan CSV (.csv)</li>
                            <li class="mb-2">Baris pertama harus berisi header kolom</li>
                            <li class="mb-2">Kolom yang dibutuhkan: nama, jenis_kelamin, kta, jabatan, tingkat</li>
                            <li class="mb-2">Hanya data dengan nama yang akan diimpor</li>
                            <li>Data dengan nama yang sudah ada akan diperbarui</li>
                        </ol>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tokoh_file" class="form-label fw-medium">File Excel</label>
                        <div class="input-group">
                            <input type="file" class="form-control @error('tokoh_file') is-invalid @enderror" 
                                id="tokoh_file" name="tokoh_file" required>
                            <span class="input-group-text"><i class="bi bi-file-earmark-excel"></i></span>
                        </div>
                        @error('tokoh_file')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                        <div class="form-text">
                            Format yang didukung: .xlsx, .xls, .csv
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i> Import Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Example Template Card -->
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white d-flex align-items-center">
                <i class="bi bi-table me-2"></i>
                <h5 class="mb-0">Contoh Format Excel</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>nama</th>
                                <th>jenis_kelamin</th>
                                <th>kta</th>
                                <th>jabatan</th>
                                <th>tingkat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Budi Santoso</td>
                                <td>Laki-laki</td>
                                <td>12345</td>
                                <td>Ketua</td>
                                <td>Pusat</td>
                            </tr>
                            <tr>
                                <td>Siti Rahayu</td>
                                <td>Perempuan</td>
                                <td>67890</td>
                                <td>Sekretaris</td>
                                <td>Daerah</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <a href="#" class="btn btn-outline-primary mt-3">
                    <i class="bi bi-download me-1"></i> Download Template
                </a>
            </div>
        </div>
    </div>
</div>
@endsection 