@extends('layouts.app')

@section('page_title', 'Dashboard')

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <span class="d-inline-block bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                    <i class="bi bi-speedometer2 fs-3"></i>
                </span>
            </div>
            <div>
                <h2 class="mb-1">Dashboard</h2>
                <p class="text-muted mb-0">Ringkasan analisis risiko media monitoring</p>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-5">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="icon"><i class="bi bi-newspaper"></i></div>
            <div class="number">{{ $stats['total_berita'] }}</div>
            <div class="title">Total Berita Dianalisis</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="icon"><i class="bi bi-person"></i></div>
            <div class="number">{{ $stats['total_tokoh'] }}</div>
            <div class="title">Tokoh Terdeteksi</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="icon"><i class="bi bi-graph-up"></i></div>
            <div class="number">{{ number_format($stats['avg_skor'], 1) }}</div>
            <div class="title">Rata-rata Skor Risiko</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="number">{{ $stats['kategori_count']['KRITIS'] + $stats['kategori_count']['TINGGI'] }}</div>
            <div class="title">Berita Risiko Tinggi/Kritis</div>
        </div>
    </div>
</div>

<!-- Data Stats -->
<div class="row mt-5">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-people me-2 text-primary"></i>
                    <h5 class="mb-0">Data Tokoh</h5>
                </div>
                <a href="{{ route('tokoh.index') }}" class="btn btn-sm btn-primary">Kelola</a>
            </div>
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center">
                    <div class="display-4 me-3 text-primary fw-bold">{{ $tokoh_count }}</div>
                    <div>
                        <h5 class="mb-1 fw-semibold">Tokoh Terdaftar</h5>
                        <p class="mb-0 text-muted">Data tokoh yang siap untuk dianalisis</p>
                    </div>
                </div>
                <div class="mt-auto pt-4">
                    <a href="{{ route('tokoh.import.form') }}" class="btn btn-outline-primary w-100">
                        <i class="bi bi-upload"></i> Import Data Tokoh
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="bi bi-newspaper me-2 text-primary"></i>
                    <h5 class="mb-0">Berita Online</h5>
                </div>
                <a href="{{ route('news.index') }}" class="btn btn-sm btn-primary">Cari</a>
            </div>
            <div class="card-body d-flex flex-column">
                <div class="text-center my-4">
                    <div class="d-inline-block bg-primary bg-opacity-10 text-primary p-4 rounded-circle mb-3">
                        <i class="bi bi-search" style="font-size: 2.5rem;"></i>
                    </div>
                    <h5 class="fw-semibold">Cari & Analisis Berita Online</h5>
                    <p class="text-muted">Cari berita terbaru dari berbagai media online dan analisis risikonya</p>
                </div>
                <div class="mt-auto">
                    <a href="{{ route('news.index') }}" class="btn btn-outline-primary w-100">
                        <i class="bi bi-search"></i> Pencarian Berita
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    <h5 class="mb-0">Status Sistem</h5>
                </div>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">
                            <i class="bi bi-clock me-2"></i>
                            Terakhir diperbarui
                        </span>
                        <span class="fw-medium">{{ now()->format('d F Y H:i') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">
                            <i class="bi bi-database me-2"></i>
                            Total data analisis
                        </span>
                        <span class="fw-medium">{{ $results->count() }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">
                            <i class="bi bi-activity me-2"></i>
                            Status sistem
                        </span>
                        <span class="badge bg-success px-3 py-2">Online</span>
                    </li>
                </ul>
                <div class="text-center mt-4">
                    <a href="{{ route('index') }}" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-2"></i> Upload & Analisis Berita
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 