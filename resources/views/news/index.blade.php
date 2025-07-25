@extends('layouts.app')

@section('page_title', 'Pencarian Berita')

@section('additional_css')
<style>
    .article-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .article-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .article-content {
        max-height: 500px;
        overflow-y: auto;
        padding: 10px;
        background-color: #f9f9f9;
        border-radius: 5px;
        border: 1px solid #e0e0e0;
    }
    
    .article-content img {
        max-width: 100%;
        height: auto;
        margin: 10px 0;
        border-radius: 5px;
        display: block;
    }
    
    .article-content p {
        margin-bottom: 1rem;
        line-height: 1.6;
    }
    
    .article-content h1, 
    .article-content h2, 
    .article-content h3, 
    .article-content h4, 
    .article-content h5, 
    .article-content h6 {
        margin-top: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .article-content figcaption {
        font-size: 0.85rem;
        color: #6c757d;
        text-align: center;
        padding: 5px 0;
    }
    
    .person-select {
        max-height: 150px;
        overflow-y: auto;
    }
    
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: 10px;
    }
    
    .article-image {
        height: 200px;
        object-fit: cover;
        width: 100%;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }
    
    .extracted-content {
        font-family: Georgia, serif;
        line-height: 1.7;
        font-size: 1.05rem;
    }
    
    .goose-content p {
        margin-bottom: 1.25rem;
    }
    
    /* Styling for article links in table */
    .article-links {
        max-height: 150px;
        overflow-y: auto;
        scrollbar-width: thin;
    }
    
    .article-links::-webkit-scrollbar {
        width: 6px;
    }
    
    .article-links::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .article-links::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 10px;
    }
    
    .article-link-item {
        transition: all 0.2s ease;
    }
    
    .article-link-item:hover {
        background-color: rgba(0,0,0,0.03);
    }
    
    .article-link-item a {
        color: #333;
    }
    
    .article-link-item a:hover {
        color: #007bff;
    }
    
    .article-link-item .badge {
        transition: all 0.2s ease;
    }
    
    .article-link-item:hover .badge {
        background-color: #007bff !important;
    }
</style>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12 mb-4">
        <h2><i class="bi bi-search"></i> Pencarian Berita</h2>
        <p class="text-muted">Cari berita dari media online dan analisis risiko</p>
    </div>
</div>

<!-- Search Form -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-search"></i> Cari Berita</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('news.index') }}" method="GET" id="searchForm">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="query" class="form-label">Kata Kunci</label>
                    <input type="text" class="form-control" id="query" name="query" value="{{ $query ?? '' }}" required placeholder="Masukkan kata kunci pencarian...">
                    <div class="form-text">Cari berita berbahasa Indonesia</div>
                </div>
                <div class="col-md-3">
                    <label for="from_date" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" value="{{ $fromDate ?? now()->subDays(3)->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label for="to_date" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" value="{{ $toDate ?? '' }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Cari
                    </button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="include_today" name="include_today" value="1" {{ $includeToday ? 'checked' : '' }}>
                        <label class="form-check-label" for="include_today">
                            Sertakan berita hari ini 
                        </label>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Results Section -->
@if(isset($articles) && count($articles) > 0)
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-newspaper"></i> Hasil Pencarian</h4>
        
        <!-- Analyze All button -->
        @if($tokohData->count() > 0)
        <button class="btn btn-success" id="analyze-all-btn">
            <i class="bi bi-graph-up"></i> Analisis Semua Berita
        </button>
        @endif
    </div>

    <div class="row g-4">
        @foreach($articles as $article)
            <div class="col-md-6 col-lg-4">
                <div class="card article-card h-100" id="article-{{ $loop->index }}">
                    <!-- Loading Overlay -->
                    <div class="loading-overlay" style="display: none;" id="loading-{{ $loop->index }}">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    
                    <!-- Article Image -->
                    @if(isset($article['urlToImage']) && !empty($article['urlToImage']))
                        <img src="{{ $article['urlToImage'] }}" class="article-image" alt="{{ $article['title'] }}">
                    @else
                        <div class="bg-light d-flex justify-content-center align-items-center article-image">
                            <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                        </div>
                    @endif
                    
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge bg-info">{{ $article['source']['name'] ?? 'Unknown' }}</span>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($article['publishedAt'])->format('d M Y') }}</small>
                        </div>
                        <h5 class="card-title">{{ $article['title'] }}</h5>
                        <p class="card-text">{{ $article['description'] }}</p>
                        
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary fetch-content-btn" 
                                data-url="{{ $article['url'] }}" 
                                data-index="{{ $loop->index }}">
                                <i class="bi bi-eye"></i> Lihat Isi Lengkap
                            </button>
                        </div>
                        
                        <!-- Article Content (initially hidden) -->
                        <div class="article-content mt-3" style="display: none;" id="content-{{ $loop->index }}"></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@elseif(isset($query))
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Tidak ditemukan hasil untuk pencarian "{{ $query }}". Coba kata kunci lain.
    </div>
@endif

<!-- Analysis Modal -->
<div class="modal fade" id="analyzeModal" tabindex="-1" aria-labelledby="analyzeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="analyzeModalLabel">Analisis Berita untuk Tokoh</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="analyzeForm">
                    <input type="hidden" id="article-url" name="url">
                    <input type="hidden" id="article-title" name="title">
                    <input type="hidden" id="article-source" name="source">
                    
                    <div class="mb-3">
                        <label for="person_id" class="form-label">Pilih Tokoh untuk Dianalisis</label>
                        <div class="border rounded p-2 person-select">
                            @if($tokohData->count() > 0)
                                @foreach($tokohData as $tokoh)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="person_id" id="person-{{ $tokoh->id }}" value="{{ $tokoh->id }}">
                                        <label class="form-check-label" for="person-{{ $tokoh->id }}">
                                            {{ $tokoh->nama }} ({{ $tokoh->jabatan ?? 'Tidak ada jabatan' }})
                                        </label>
                                    </div>
                                @endforeach
                            @else
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle"></i> Tidak ada data tokoh. 
                                    <a href="{{ route('tokoh.import.form') }}" class="alert-link">Import data tokoh</a> terlebih dahulu.
                                </div>
                            @endif
                        </div>
                    </div>
                </form>
                
                <div id="analysis-result" class="mt-3" style="display: none;">
                    <div class="alert alert-success">
                        <p class="mb-1"><strong>Skor Risiko:</strong> <span id="result-score"></span></p>
                        <p class="mb-1"><strong>Kategori:</strong> <span id="result-category"></span></p>
                        <p class="mb-0"><strong>Rekomendasi:</strong> <span id="result-recommendation"></span></p>
                    </div>
                </div>
                
                <div id="analysis-error" class="mt-3 alert alert-danger" style="display: none;"></div>
                
                <div class="modal-loading text-center py-3" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Menganalisis berita...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="analyze-btn" @if($tokohData->count() == 0) disabled @endif>Analisis</button>
            </div>
        </div>
    </div>
</div>

<!-- Analyze All Modal -->
<div class="modal fade" id="analyzeAllModal" tabindex="-1" aria-labelledby="analyzeAllModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="analyzeAllModalLabel">Analisis Semua Berita untuk Semua Tokoh</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="analyze-all-loading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 mb-0">Sedang menganalisis artikel untuk semua tokoh...</p>
                    <p class="text-muted">Hal ini mungkin memerlukan waktu beberapa saat</p>
                    <div class="progress mt-3">
                        <div id="analyze-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="mt-2" id="analyze-progress-text">0/0 artikel selesai dianalisis</p>
                </div>

                <div id="analyze-all-result" style="display: none;">
                    <div class="alert alert-success mb-3">
                        <h5 class="mb-1"><i class="bi bi-check-circle"></i> Analisis selesai!</h5>
                        <p class="mb-0"><span id="analyzed-count">0</span> tokoh telah dianalisis dari beberapa artikel berita</p>
                    </div>

                    <h5>Hasil Analisis:</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nama Tokoh</th>
                                    <th>Skor Risiko</th>
                                    <th>Kategori</th>
                                    <th>Urgensi</th>
                                    <th>Jumlah Artikel</th>
                                    <th style="width: 280px;">Link Artikel</th>
                                </tr>
                            </thead>
                            <tbody id="analyze-all-results-table">
                                <!-- Results will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="analyze-all-error" class="alert alert-danger mt-3" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <a href="{{ route('analysis') }}" class="btn btn-primary">Lihat Semua Hasil</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch full article content
        const fetchContentButtons = document.querySelectorAll('.fetch-content-btn');
        fetchContentButtons.forEach(button => {
            button.addEventListener('click', function() {
                const url = this.getAttribute('data-url');
                const index = this.getAttribute('data-index');
                const contentDiv = document.getElementById('content-' + index);
                const loadingDiv = document.getElementById('loading-' + index);
                
                if (!contentDiv) return;
                
                // Toggle content if already loaded
                if (contentDiv.innerHTML.trim() !== '') {
                    contentDiv.style.display = contentDiv.style.display === 'none' ? 'block' : 'none';
                    return;
                }
                
                // Show loading
                if (loadingDiv) loadingDiv.style.display = 'flex';
                
                // Fetch content
                fetch('{{ route("news.fetch-full") }}?url=' + encodeURIComponent(url))
                    .then(response => response.json())
                    .then(data => {
                        if (data.content) {
                            if (contentDiv) {
                                // Add extraction method info for debugging
                                let methodInfo = '';
                                if (data.method) {
                                    methodInfo = `<div class="alert alert-info small mb-2">
                                        <i class="bi bi-info-circle"></i> Extracted using: <strong>${data.method}</strong>
                                    </div>`;
                                }
                                
                                // Check if content contains only images or very little text
                                const contentText = data.content.replace(/<img[^>]*>/g, '');
                                const plainText = contentText.replace(/<[^>]*>/g, '').trim();
                                
                                if (plainText.length < 200) {
                                    // Display warning for poor content extraction
                                    contentDiv.innerHTML = `
                                        ${methodInfo}
                                        <div class="alert alert-warning">
                                            <i class="bi bi-exclamation-triangle"></i> 
                                            Konten teks tidak dapat diambil dengan baik. Silakan kunjungi 
                                            <a href="${url}" target="_blank" class="alert-link">situs asli</a> untuk membaca artikel lengkap.
                                        </div>
                                        <div class="extracted-content mt-3">${data.content}</div>`;
                                } else {
                                    contentDiv.innerHTML = `${methodInfo}${data.content}`;
                                }
                                
                                contentDiv.style.display = 'block';
                            }
                        } else {
                            if (contentDiv) {
                                contentDiv.innerHTML = '<div class="alert alert-warning">Konten tidak dapat diambil</div>';
                                contentDiv.style.display = 'block';
                            }
                        }
                    })
                    .catch(error => {
                        if (contentDiv) {
                            contentDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
                            contentDiv.style.display = 'block';
                        }
                    })
                    .finally(() => {
                        if (loadingDiv) loadingDiv.style.display = 'none';
                        
                        // Enable the global analyze all button
                        const analyzeAllBtn = document.getElementById('analyze-all-btn');
                        if (analyzeAllBtn && contentDiv && contentDiv.style.display === 'block' && contentDiv.innerHTML.trim() !== '') {
                            analyzeAllBtn.disabled = false;
                        }
                    });
            });
        });
        
        // Analysis Modal
        const analyzeModal = document.getElementById('analyzeModal');
        if (analyzeModal) {
            analyzeModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const url = button.getAttribute('data-url');
                const title = button.getAttribute('data-title');
                const source = button.getAttribute('data-source');
                
                const articleUrlInput = document.getElementById('article-url');
                const articleTitleInput = document.getElementById('article-title');
                const articleSourceInput = document.getElementById('article-source');
                const analyzeForm = document.getElementById('analyzeForm');
                const analysisResult = document.getElementById('analysis-result');
                const analysisError = document.getElementById('analysis-error');
                
                if (articleUrlInput) articleUrlInput.value = url;
                if (articleTitleInput) articleTitleInput.value = title;
                if (articleSourceInput) articleSourceInput.value = source;
                
                // Reset form and results
                if (analyzeForm) analyzeForm.reset();
                if (analysisResult) analysisResult.style.display = 'none';
                if (analysisError) analysisError.style.display = 'none';
            });
        }
        
        // Analyze Button
        const analyzeBtn = document.getElementById('analyze-btn');
        if (analyzeBtn) {
            analyzeBtn.addEventListener('click', function() {
                const form = document.getElementById('analyzeForm');
                if (!form) return;
                
                const personId = form.querySelector('input[name="person_id"]:checked');
                
                if (!personId) {
                    alert('Pilih tokoh terlebih dahulu');
                    return;
                }
                
                // Show loading
                const loadingDiv = document.querySelector('.modal-loading');
                const analysisResult = document.getElementById('analysis-result');
                const analysisError = document.getElementById('analysis-error');
                
                if (loadingDiv) loadingDiv.style.display = 'block';
                if (analysisResult) analysisResult.style.display = 'none';
                if (analysisError) analysisError.style.display = 'none';
                if (analyzeBtn) analyzeBtn.disabled = true;
                
                // Prepare data
                const data = {
                    url: document.getElementById('article-url')?.value,
                    person_id: personId.value,
                    title: document.getElementById('article-title')?.value,
                    source: document.getElementById('article-source')?.value,
                    search_query: new URLSearchParams(window.location.search).get('query') || ''
                };
                
                // Send analysis request
                fetch('{{ route("news.analyze") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        const errorElement = document.getElementById('analysis-error');
                        if (errorElement) {
                            errorElement.textContent = data.error;
                            errorElement.style.display = 'block';
                        }
                    } else if (data.success) {
                        const scoreElement = document.getElementById('result-score');
                        const categoryElement = document.getElementById('result-category');
                        const recommendationElement = document.getElementById('result-recommendation');
                        const resultElement = document.getElementById('analysis-result');
                        
                        if (scoreElement) scoreElement.textContent = data.analysis.skor_risiko + ' (' + data.analysis.persentase_kerawanan + ')';
                        if (categoryElement) categoryElement.textContent = data.analysis.kategori;
                        if (recommendationElement) recommendationElement.textContent = data.analysis.rekomendasi;
                        if (resultElement) resultElement.style.display = 'block';
                    }
                })
                .catch(error => {
                    const errorElement = document.getElementById('analysis-error');
                    if (errorElement) {
                        errorElement.textContent = 'Error: ' + error.message;
                        errorElement.style.display = 'block';
                    }
                })
                .finally(() => {
                    const loadingDiv = document.querySelector('.modal-loading');
                    if (loadingDiv) loadingDiv.style.display = 'none';
                    if (analyzeBtn) analyzeBtn.disabled = false;
                });
            });
        }

        // Global "Analyze All" button
        const analyzeAllBtn = document.getElementById('analyze-all-btn');
        if (analyzeAllBtn) {
            analyzeAllBtn.addEventListener('click', function() {
                // Get all articles
                const articles = document.querySelectorAll('.article-card');
                
                if (articles.length === 0) {
                    alert('Tidak ada artikel yang tersedia');
                    return;
                }
                
                // Show analyze all modal
                const analyzeAllModal = new bootstrap.Modal(document.getElementById('analyzeAllModal'));
                analyzeAllModal.show();
                
                // Show loading
                const loadingElement = document.getElementById('analyze-all-loading');
                const resultElement = document.getElementById('analyze-all-result');
                const errorElement = document.getElementById('analyze-all-error');
                
                if (loadingElement) loadingElement.style.display = 'block';
                if (resultElement) resultElement.style.display = 'none';
                if (errorElement) errorElement.style.display = 'none';
                
                // Update loading message
                if (loadingElement) {
                    loadingElement.innerHTML = `
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 mb-0">Sedang menganalisis ${articles.length} artikel untuk semua tokoh...</p>
                        <p class="text-muted">Hal ini mungkin memerlukan waktu beberapa saat</p>
                        <div class="progress mt-3">
                            <div id="analyze-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>
                        <p class="mt-2" id="analyze-progress-text">0/${articles.length} artikel selesai dianalisis</p>
                    `;
                }
                
                // Array to store all results
                let allResults = [];
                let completedCount = 0;
                let errorCount = 0;
                let skippedArticles = [];
                
                // Get the search query from the URL
                const urlParams = new URLSearchParams(window.location.search);
                const searchQuery = urlParams.get('query') || '';

                // Process each article sequentially with better error handling
                function processNextArticle(index) {
                    if (index >= articles.length) {
                        // All articles processed, show final results
                        showFinalResults(allResults, errorCount, skippedArticles);
                        return;
                    }
                    
                    const article = articles[index];
                    const contentButton = article.querySelector('.fetch-content-btn');
                    
                    if (!contentButton) {
                        // Skip if no button found, move to next article
                        processNextArticle(index + 1);
                        return;
                    }
                    
                    const url = contentButton.getAttribute('data-url');
                    const title = article.querySelector('.card-title').textContent;
                    const source = article.querySelector('.badge').textContent;
                    
                    // Sites that need special handling
                    const problematicSites = [
                        'health.detik.com', 
                        'detik.com/berita',
                        'inews.id/lifestyle',
                        'inews.id/health',
                        'suara.com', 
                        'liputan6.com',
                        'kompas.tv',
                        'cnnindonesia.com',
                        'tribunnews.com'
                    ];
                    const isProblematicSite = problematicSites.some(site => url.includes(site));
                    
                    // Prepare data - use title-only mode for problematic sites
                    const data = {
                        url: url,
                        title: title,
                        source: source,
                        search_query: searchQuery,
                        title_only: isProblematicSite // Special flag for problematic sites
                    };
                    
                    // Visual indicator for problematic sites
                    if (isProblematicSite) {
                        const indicator = document.createElement('div');
                        indicator.className = 'position-absolute top-0 start-0 mt-2 ms-2';
                        indicator.innerHTML = `
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-exclamation-triangle"></i> Limited Mode
                            </span>
                        `;
                        article.style.position = 'relative';
                        article.appendChild(indicator);
                    }
                    
                    // Send analysis request for this article
                    fetch('{{ route("news.analyze-all") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(errorData => {
                                throw new Error(errorData.error || `HTTP error ${response.status}`);
                            }).catch(jsonError => {
                                // If JSON parsing fails, throw the original HTTP error
                                throw new Error(`HTTP error ${response.status}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Even if no people are found, we still consider it a success for this article
                        if (data.success) {
                            if (data.results && data.results.length > 0) {
                                // Add source and title information to each result
                                const resultsWithSource = data.results.map(result => {
                                    return {
                                        ...result,
                                        source: source,
                                        title: title,
                                        url: url
                                    };
                                });
                                
                                // Add to all results
                                allResults = allResults.concat(resultsWithSource);
                            }
                            // We don't display any error if no people were found for this specific article
                        } else if (data.error) {
                            console.warn(`Article analysis issue for ${url}: ${data.error}`);
                            errorCount++;
                        }
                        
                        // Mark article as processed visually
                        markArticleProcessed(article, 'success');
                        
                        // Update progress
                        completedCount++;
                        const progressPercent = (completedCount / articles.length) * 100;
                        updateProgress(progressPercent, completedCount, articles.length);
                        
                        // Add small delay before processing next article
                        setTimeout(() => {
                            processNextArticle(index + 1);
                        }, 500);
                    })
                    .catch(error => {
                        console.error('Error analyzing article:', error);
                        
                        // Log error details to help with debugging
                        const errorMessage = error.message || 'Unknown error';
                        console.warn(`Failed to analyze article at ${url}: ${errorMessage}`);
                        
                        // Mark article as error visually
                        markArticleProcessed(article, 'error', errorMessage);
                        
                        // Add to error count
                        errorCount++;
                        
                        // Update progress and continue
                        completedCount++;
                        const progressPercent = (completedCount / articles.length) * 100;
                        updateProgress(progressPercent, completedCount, articles.length);
                        
                        // Add delay before processing next article to avoid overwhelming the server
                        setTimeout(() => {
                            processNextArticle(index + 1);
                        }, 1500); // Increase delay to 1.5 seconds on error
                    });
                }
                
                // Helper function to mark article cards visually
                function markArticleProcessed(article, status, message = '') {
                    if (status === 'success') {
                        article.classList.add('border-success');
                        const successBadge = document.createElement('div');
                        successBadge.className = 'position-absolute top-0 end-0 mt-2 me-2';
                        successBadge.innerHTML = `
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle"></i> Processed
                            </span>
                        `;
                        article.style.position = 'relative';
                        article.appendChild(successBadge);
                    } else if (status === 'error') {
                        article.classList.add('border-danger');
                        const errorBadge = document.createElement('div');
                        errorBadge.className = 'position-absolute top-0 end-0 mt-2 me-2';
                        errorBadge.innerHTML = `
                            <span class="badge bg-danger" title="${message}">
                                <i class="bi bi-exclamation-triangle"></i> Error
                            </span>
                        `;
                        article.style.position = 'relative';
                        article.appendChild(errorBadge);
                    }
                }
                
                // Helper function to update progress
                function updateProgress(percent, completed, total) {
                    const progressBar = document.getElementById('analyze-progress-bar');
                    const progressText = document.getElementById('analyze-progress-text');
                    
                    if (progressBar) {
                        progressBar.style.width = percent + '%';
                    }
                    
                    if (progressText) {
                        progressText.textContent = `${completed}/${total} artikel selesai dianalisis`;
                    }
                }
                
                // Start processing articles
                processNextArticle(0);
                
                // Function to display final results
                function showFinalResults(results, errorCount, skippedArticles) {
                    const errorElement = document.getElementById('analyze-all-error');
                    const loadingElement = document.getElementById('analyze-all-loading');
                    const resultElement = document.getElementById('analyze-all-result');
                    const analyzedCountElement = document.getElementById('analyzed-count');
                    const resultsTableBody = document.getElementById('analyze-all-results-table');
                    
                    // Show errors if any
                    if (errorCount > 0 || skippedArticles.length > 0) {
                        let errorMessage = '';
                        
                        if (errorCount > 0) {
                            errorMessage += `<p><strong>${errorCount} artikel</strong> gagal dianalisis karena masalah teknis.</p>`;
                        }
                        
                        if (skippedArticles.length > 0) {
                            errorMessage += `<p><strong>${skippedArticles.length} artikel</strong> dilewati karena diketahui problematik:</p>`;
                            errorMessage += '<ul>';
                            skippedArticles.slice(0, 5).forEach(article => {
                                errorMessage += `<li>${article.title} <a href="${article.url}" target="_blank">(link)</a></li>`;
                            });
                            if (skippedArticles.length > 5) {
                                errorMessage += `<li>...dan ${skippedArticles.length - 5} artikel lainnya</li>`;
                            }
                            errorMessage += '</ul>';
                        }
                        
                        if (errorElement) {
                            errorElement.innerHTML = errorMessage;
                            errorElement.style.display = 'block';
                        }
                    }
                    
                    if (results.length === 0) {
                        if (errorElement && !errorElement.innerHTML) {
                            errorElement.textContent = 'Tidak ada tokoh yang ditemukan di seluruh artikel. Pastikan data tokoh sudah diimpor dan nama tokoh ditulis dengan benar dalam berita.';
                            errorElement.style.display = 'block';
                        }
                        if (loadingElement) {
                            loadingElement.style.display = 'none';
                        }
                        return;
                    }
                    
                    // Group results by person name
                    const groupedResults = {};
                    results.forEach(result => {
                        if (!groupedResults[result.name]) {
                            groupedResults[result.name] = [];
                        }
                        groupedResults[result.name].push(result);
                    });
                    
                    // Update result count
                    if (analyzedCountElement) {
                        analyzedCountElement.textContent = Object.keys(groupedResults).length;
                    }
                    
                    // Build results table
                    if (resultsTableBody) {
                        resultsTableBody.innerHTML = '';
                        
                        Object.keys(groupedResults).forEach(name => {
                            // For each person, find the highest risk score
                            const personResults = groupedResults[name];
                            let highestRiskResult = personResults[0];
                            
                            personResults.forEach(result => {
                                if (result.score > highestRiskResult.score) {
                                    highestRiskResult = result;
                                }
                            });
                            
                            // Ensure urgency is set based on category and score if not present
                            if (!highestRiskResult.urgency || highestRiskResult.urgency === '') {
                                if (highestRiskResult.category === 'KRITIS') {
                                    highestRiskResult.urgency = 'DARURAT';
                                } else if (highestRiskResult.category === 'TINGGI') {
                                    // All TINGGI category items get SEGERA urgency to match backend logic
                                    highestRiskResult.urgency = 'SEGERA';
                                } else if (highestRiskResult.category === 'SEDANG') {
                                    highestRiskResult.urgency = 'PERHATIAN';
                                } else {
                                    highestRiskResult.urgency = 'MONITORING';
                                }
                            }
                            
                            // Create a row with the highest risk score
                            const row = document.createElement('tr');
                            
                            // Set row class based on urgency level
                            if (highestRiskResult.urgency === 'DARURAT') {
                                row.classList.add('table-danger');
                            } else if (highestRiskResult.urgency === 'SEGERA') {
                                row.classList.add('table-warning');
                            } else if (highestRiskResult.urgency === 'PERHATIAN') {
                                row.classList.add('table-info');
                            } else {
                                row.classList.add('table-success');
                            }
                            
                            row.innerHTML = `
                                <td>${name}</td>
                                <td>${highestRiskResult.score}</td>
                                <td><span class="badge bg-${highestRiskResult.category === 'KRITIS' ? 'danger' : (highestRiskResult.category === 'TINGGI' ? 'warning' : (highestRiskResult.category === 'SEDANG' ? 'info' : 'success'))}">${highestRiskResult.category}</span></td>
                                <td><span class="badge bg-${highestRiskResult.urgency === 'DARURAT' ? 'danger' : (highestRiskResult.urgency === 'SEGERA' ? 'warning' : (highestRiskResult.urgency === 'PERHATIAN' ? 'info' : 'secondary'))}">${highestRiskResult.urgency}</span></td>
                                <td>${personResults.length}</td>
                                <td>
                                    <div class="article-links" style="max-height: 150px; overflow-y: auto;">
                                        ${personResults.map(r => `
                                            <div class="article-link-item mb-2">
                                                <a href="${r.url}" target="_blank" class="d-flex align-items-start text-decoration-none">
                                                    <div class="flex-shrink-0">
                                                        <span class="badge rounded-pill bg-secondary me-1">
                                                            <i class="bi bi-link-45deg"></i>
                                                        </span>
                                                    </div>
                                                    <div class="ms-1 text-truncate small" style="max-width: 250px;" title="${r.title}">
                                                        ${r.title.substring(0, 60)}${r.title.length > 60 ? '...' : ''}
                                                    </div>
                                                </a>
                                            </div>
                                        `).join('')}
                                    </div>
                                </td>
                            `;
                            
                            resultsTableBody.appendChild(row);
                        });
                    }
                    
                    // Show results
                    if (resultElement) resultElement.style.display = 'block';
                    if (loadingElement) loadingElement.style.display = 'none';
                }
            });
        }
    });
</script>
@endsection 