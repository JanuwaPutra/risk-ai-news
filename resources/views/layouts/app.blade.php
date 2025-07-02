<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potential Risk Intelligence - Media Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --secondary-color: #7209b7;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-color: #f3f4f6;
            --border-radius: 12px;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            --font-size-base: 0.9rem;
            --font-size-sm: 0.82rem;
            --font-size-xs: 0.75rem;
            --font-size-lg: 1rem;
            --font-size-xl: 1.15rem;
            --font-size-2xl: 1.35rem;
        }
        
        body {
            padding: 0;
            margin: 0;
            background-color: #f8fafc;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            overflow-x: hidden;
            font-size: var(--font-size-base);
        }
        
        /* Adjust general text sizes */
        h1, .h1 { font-size: var(--font-size-2xl) !important; }
        h2, .h2 { font-size: calc(var(--font-size-2xl) - 0.1rem) !important; }
        h3, .h3 { font-size: var(--font-size-xl) !important; }
        h4, .h4 { font-size: var(--font-size-lg) !important; }
        h5, .h5 { font-size: var(--font-size-base) !important; }
        
        .form-label { font-size: var(--font-size-sm); }
        .form-text { font-size: var(--font-size-xs); }
        .btn { font-size: var(--font-size-sm); }
        
        /* Icon circle fix */
        .rounded-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 !important;
            aspect-ratio: 1/1;
        }
        
        /* Memperbaiki posisi ikon dalam lingkaran */
        .rounded-circle i {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            line-height: 1;
        }
        
        .rounded-circle i.fs-3 {
            font-size: 1.5rem !important;
        }
        
        /* Ukuran lingkaran */
        .p-3.rounded-circle {
            width: 50px;
            height: 50px;
        }
        
        .p-4.rounded-circle {
            width: 70px;
            height: 70px;
        }
        
        /* DataTables adjustments */
        .dataTables_info, 
        .dataTables_length, 
        .dataTables_filter, 
        .paginate_button {
            font-size: var(--font-size-sm) !important;
        }
        
        .table th, 
        .table td {
            font-size: var(--font-size-sm);
            padding: 0.6rem 0.75rem;
        }
        
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, #3a56d4, #4361ee);
            color: #fff;
            z-index: 1000;
            transition: all 0.3s;
            box-shadow: var(--shadow-lg);
            overflow-y: auto;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            margin-bottom: 0.25rem;
            font-weight: 700;
            font-size: 1.3rem;
            letter-spacing: -0.5px;
        }
        
        .sidebar-menu {
            padding: 1.5rem 0;
        }
        
        .nav-link {
            padding: 0.8rem 1.5rem;
            color: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            transition: all 0.3s;
            border-radius: 8px;
            margin: 0.3rem 1rem;
            font-size: var(--font-size-sm);
        }
        
        .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }
        
        .nav-link.active {
            color: var(--primary-dark);
            background-color: #fff;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }
        
        .nav-link i {
            margin-right: 12px;
            width: 24px;
            text-align: center;
            font-size: 1rem;
        }
        
        /* Main content area */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Navbar */
        .top-navbar {
            background-color: #fff;
            box-shadow: var(--shadow-sm);
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .hamburger-btn {
            cursor: pointer;
            display: none;
            font-size: 1.5rem;
            color: var(--dark-color);
        }
        
        /* Cards and other UI elements */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            border-bottom: none;
            padding: 0.8rem 1.2rem;
            font-weight: 600;
            background: transparent;
        }
        
        .card-body {
            padding: 1rem 1.2rem;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.25rem;
            text-align: center;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .stat-card .number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--primary-color);
        }
        
        .stat-card .title {
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .stat-card .icon {
            position: absolute;
            top: 12px;
            right: 12px;
            font-size: 1.5rem;
            opacity: 0.1;
            color: var(--primary-color);
        }
        
        /* Progress bars */
        .progress {
            height: 20px;
            margin-bottom: 15px;
            border-radius: 50px;
            background-color: #f1f5f9;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .progress-bar {
            border-radius: 50px;
            color: #fff;
            font-weight: 600;
            font-size: var(--font-size-xs);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: width 0.6s ease;
        }
        
        /* Badge styles */
        .urgency-monitoring {
            background-color: var(--info-color);
        }
        
        .urgency-perhatian {
            background-color: var(--warning-color);
        }
        
        .urgency-segera {
            background-color: #fb923c;
        }
        
        .urgency-darurat {
            background-color: var(--danger-color);
        }
        
        .badge {
            padding: 0.4rem 0.7rem;
            font-weight: 600;
            border-radius: 50px;
            font-size: 0.75rem;
        }
        
        .badge-rendah {
            background-color: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }
        
        .badge-sedang {
            background-color: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }
        
        .badge-tinggi {
            background-color: rgba(251, 146, 60, 0.15);
            color: #fb923c;
        }
        
        .badge-kritis {
            background-color: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }
        
        /* Table styles */
        .results-table {
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            border-top: none;
            border-bottom-width: 1px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: var(--font-size-xs);
            letter-spacing: 0.5px;
            color: #64748b;
        }
        
        .risk-low {
            background-color: rgba(16, 185, 129, 0.05);
        }
        
        .risk-medium {
            background-color: rgba(245, 158, 11, 0.05);
        }
        
        .risk-high {
            background-color: rgba(251, 146, 60, 0.05);
        }
        
        .risk-critical {
            background-color: rgba(239, 68, 68, 0.05);
        }
        
        /* DataTables customization */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.4rem 0.8rem;
            margin-left: 0.5rem;
            font-size: var(--font-size-sm);
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
            outline: none;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.4rem;
            margin: 0 0.5rem;
            font-size: var(--font-size-sm);
        }
        
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
            outline: none;
        }
        
        .dataTables_wrapper .dataTables_info {
            padding-top: 1rem;
            font-size: var(--font-size-sm);
            color: #64748b;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 8px;
            margin: 0 0.2rem;
            font-size: var(--font-size-sm);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: white !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            color: var(--primary-color) !important;
        }
        
        /* Form sections */
        .upload-section, .progress-container, .filter-section {
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.75rem;
            margin-bottom: 2rem;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.65rem 0.9rem;
            font-size: var(--font-size-sm);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        
        .btn {
            border-radius: 8px;
            padding: 0.65rem 1.25rem;
            font-weight: 500;
            transition: transform 0.15s, box-shadow 0.15s;
            font-size: var(--font-size-sm);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.15);
        }
        
        /* Loading indicator */
        #loading-indicator {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .spinner-container {
            background-color: white;
            padding: 2.5rem;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow-lg);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                margin-left: -280px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.active {
                margin-left: 280px;
            }
            
            .hamburger-btn {
                display: block;
            }
        }
    </style>
    @yield('additional_css')
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Risk Intelligence</h3>
            <p class="mb-0 small text-light-50">Media Monitoring</p>
        </div>
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('analysis') ? 'active' : '' }}" href="{{ route('analysis') }}">
                        <i class="bi bi-graph-up"></i> Analisis AI
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('news.*') ? 'active' : '' }}" href="{{ route('news.index') }}">
                        <i class="bi bi-newspaper"></i> Pencarian Berita
                    </a>
                </li>
        
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('tokoh.*') ? 'active' : '' }}" href="{{ route('tokoh.index') }}">
                        <i class="bi bi-people"></i> Kelola Data Tokoh
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('index') ? 'active' : '' }}" href="{{ route('index') }}">
                        <i class="bi bi-upload"></i> Upload & Analisis
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="hamburger-btn me-3">
                    <i class="bi bi-list"></i>
                </div>
                <h4 class="mb-0">@yield('page_title', 'Potential Risk Intelligence')</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="user-info d-flex align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-light text-dark me-2">
                            <i class="bi bi-clock"></i> {{ date('d M Y') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                {{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')

        <footer class="text-center mt-5 py-4 text-muted">
            <p class="mb-0">Potential Risk Intelligence &copy; {{ date('Y') }}</p>
        </footer>
    </div>

    <!-- Loading Indicator -->
    <div id="loading-indicator" style="display: none;">
        <div class="spinner-container">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 class="mt-3">Sedang Memproses...</h5>
            <p class="text-muted">Harap tunggu sementara sistem menganalisis data.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    
    <script>
        // Toggle sidebar
        $(document).ready(function() {
            $('.hamburger-btn').on('click', function() {
                $('.sidebar').toggleClass('active');
                $('.main-content').toggleClass('active');
            });
            
            // Show loading indicator when forms are submitted
            $("#upload-form").on("submit", function() {
                $("#loading-indicator").show();
            });
            
            $("#filter-form").on("submit", function() {
                $("#loading-indicator").show();
            });
        });
    </script>
    
    @yield('scripts')
</body>
</html>
