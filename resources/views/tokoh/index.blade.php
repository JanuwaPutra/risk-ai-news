@extends('layouts.app')

@section('page_title', 'Kelola Data Tokoh')

@section('additional_css')
<style>
    .editable {
        cursor: pointer;
        padding: 5px;
        border-radius: 3px;
        transition: background-color 0.2s;
    }
    
    .editable:hover {
        background-color: #f8f9fa;
    }
    
    .editable.editing {
        background-color: #e9ecef;
        padding: 0;
    }
    
    .editable.highlight {
        animation: highlight-fade 2s;
    }
    
    @keyframes highlight-fade {
        0% { background-color: #d4edda; }
        100% { background-color: transparent; }
    }
    
    .edit-input {
        width: 100%;
        padding: 5px;
        border: 1px solid #ced4da;
        border-radius: 3px;
    }
    
    .edit-textarea {
        width: 100%;
        min-height: 80px;
        padding: 5px;
        border: 1px solid #ced4da;
        border-radius: 3px;
    }
    
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
    
    .card {
        margin-bottom: 2rem;
    }
    
    .card-body {
        padding: 1.75rem;
    }
    
    .action-buttons {
        gap: 0.5rem;
    }
</style>
@endsection

@section('content')
<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <span class="d-inline-block bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                    <i class="bi bi-people fs-3"></i>
                </span>
            </div>
            <div>
                <h2 class="mb-1">Kelola Data Tokoh</h2>
                <p class="text-muted mb-0">Mengelola data tokoh untuk analisis berita</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Data Tokoh</h5>
        <div class="d-flex action-buttons">
            <a href="{{ route('tokoh.import.form') }}" class="btn btn-sm btn-light me-2">
                <i class="bi bi-upload"></i> Import Data
            </a>
            <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAllModal">
                <i class="bi bi-trash"></i> Hapus Semua Data
            </a>
        </div>
    </div>
    
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle me-2"></i> Klik dua kali pada semua kolom untuk mengedit data. Untuk Alias, gunakan koma, titik koma, atau baris baru untuk memisahkan beberapa alias.
        </div>
        
        <div class="mb-4">
            <h5 class="mb-3">Filter Tabel:</h5>
            <div class="table-filters">
                <div class="table-filter-item">
                    <div class="filter-chip" data-column="gender" data-value="Laki-laki">
                        <i class="bi bi-gender-male text-primary"></i> Laki-laki
                    </div>
                </div>
                <div class="table-filter-item">
                    <div class="filter-chip" data-column="gender" data-value="Perempuan">
                        <i class="bi bi-gender-female text-danger"></i> Perempuan
                    </div>
                </div>
                <div class="table-filter-item">
                    <div class="filter-chip" data-column="tingkat" data-value="Pusat">
                        <i class="bi bi-geo-alt text-success"></i> Pusat
                    </div>
                </div>
                <div class="table-filter-item">
                    <div class="filter-chip" data-column="tingkat" data-value="Daerah">
                        <i class="bi bi-geo-alt text-warning"></i> Daerah
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
            <table class="table table-striped table-hover" id="tokohTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>Alias</th>
                        <th>Jenis Kelamin</th>
                        <th>KTA</th>
                        <th>Jabatan</th>
                        <th>Tingkat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tokohData as $index => $tokoh)
                        <tr data-gender="{{ $tokoh->jenis_kelamin }}" data-tingkat="{{ $tokoh->tingkat }}">
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <div class="editable" data-id="{{ $tokoh->id }}" data-field="nama" data-type="input">
                                    {{ $tokoh->nama }}
                                </div>
                            </td>
                            <td>
                                <div class="editable" data-id="{{ $tokoh->id }}" data-field="alias" data-type="textarea">
                                    {{ $tokoh->alias ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <div class="editable" data-id="{{ $tokoh->id }}" data-field="jenis_kelamin" data-type="input">
                                    {{ $tokoh->jenis_kelamin ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <div class="editable" data-id="{{ $tokoh->id }}" data-field="kta" data-type="input">
                                    {{ $tokoh->kta ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <div class="editable" data-id="{{ $tokoh->id }}" data-field="jabatan" data-type="input">
                                    {{ $tokoh->jabatan ?? '-' }}
                                </div>
                            </td>
                            <td>
                                <div class="editable" data-id="{{ $tokoh->id }}" data-field="tingkat" data-type="input">
                                    {{ $tokoh->tingkat ?? '-' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="alert alert-info mb-0">
                                    Tidak ada data tokoh yang tersedia. 
                                    <a href="{{ route('tokoh.import.form') }}" class="alert-link">
                                        Import data sekarang
                                    </a>.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete All Confirmation Modal -->
<div class="modal fade" id="deleteAllModal" tabindex="-1" aria-labelledby="deleteAllModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteAllModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menghapus SEMUA data tokoh? Tindakan ini tidak dapat dibatalkan.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form action="{{ route('tokoh.delete.all') }}" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Hapus Semua Data</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup CSRF token for AJAX requests
        const csrfToken = '{{ csrf_token() }}';
        
        // Initialize DataTable
        var dataTable = $('#tokohTable').DataTable({
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
                { responsivePriority: 1, targets: [1, 5, 6] }, // Prioritas kolom yang ditampilkan di responsive view
                { responsivePriority: 2, targets: [0, 3] }
            ]
        });
        
        // Custom filtering function for gender and tingkat
        $.fn.dataTable.ext.search.push(
            function(settings, data, dataIndex) {
                var $row = $(dataTable.row(dataIndex).node());
                var genderFilter = $('#genderFilter').val();
                var tingkatFilter = $('#tingkatFilter').val();
                
                // If no filters are active, show all rows
                if (!genderFilter && !tingkatFilter) {
                    return true;
                }
                
                var rowGender = $row.data('gender');
                var rowTingkat = $row.data('tingkat');
                
                // Apply gender filter if active
                if (genderFilter && rowGender !== genderFilter) {
                    return false;
                }
                
                // Apply tingkat filter if active
                if (tingkatFilter && rowTingkat !== tingkatFilter) {
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
                $('#genderFilter, #tingkatFilter').val('');
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
                    if (column === 'gender') {
                        $('#genderFilter').val(value);
                    } else if (column === 'tingkat') {
                        $('#tingkatFilter').val(value);
                    }
                } else {
                    // Clear this filter value
                    if (column === 'gender') {
                        $('#genderFilter').val('');
                    } else if (column === 'tingkat') {
                        $('#tingkatFilter').val('');
                    }
                }
            }
            
            // Redraw the table with the new filters
            dataTable.draw();
        });
        
        // Add hidden fields for tracking active filters
        $('body').append('<input type="hidden" id="genderFilter" value="">');
        $('body').append('<input type="hidden" id="tingkatFilter" value="">');
        
        // Handle inline editing using event delegation
        // This will work for all pages of the DataTable, not just the first page
        $(document).on('dblclick', '.editable', function() {
            const id = this.getAttribute('data-id');
            const field = this.getAttribute('data-field');
            const type = this.getAttribute('data-type') || 'input';
            const currentValue = this.textContent.trim();
            const displayValue = currentValue === '-' ? '' : currentValue;
            
            // Store original value for reverting if needed
            this.setAttribute('data-original-value', currentValue);
            
            // Add editing class
            this.classList.add('editing');
            
            // Create input element
            let inputElement;
            
            if (field === 'jenis_kelamin') {
                // Dropdown for Jenis Kelamin
                inputElement = document.createElement('select');
                inputElement.classList.add('edit-input', 'form-select');
                
                const options = [
                    { value: '', text: '-- Pilih Jenis Kelamin --' },
                    { value: 'Laki-laki', text: 'Laki-laki' },
                    { value: 'Perempuan', text: 'Perempuan' }
                ];
                
                options.forEach(option => {
                    const optElement = document.createElement('option');
                    optElement.value = option.value;
                    optElement.textContent = option.text;
                    if (option.value === displayValue) {
                        optElement.selected = true;
                    }
                    inputElement.appendChild(optElement);
                });
            } else if (field === 'tingkat') {
                // Dropdown for Tingkat
                inputElement = document.createElement('select');
                inputElement.classList.add('edit-input', 'form-select');
                
                const options = [
                    { value: '', text: '-- Pilih Tingkat --' },
                    { value: 'Pusat', text: 'Pusat' },
                    { value: 'Daerah', text: 'Daerah' }
                ];
                
                options.forEach(option => {
                    const optElement = document.createElement('option');
                    optElement.value = option.value;
                    optElement.textContent = option.text;
                    if (option.value === displayValue) {
                        optElement.selected = true;
                    }
                    inputElement.appendChild(optElement);
                });
            } else if (type === 'textarea') {
                inputElement = document.createElement('textarea');
                inputElement.classList.add('edit-textarea');
                inputElement.rows = 3;
                inputElement.value = displayValue;
            } else {
                inputElement = document.createElement('input');
                inputElement.classList.add('edit-input');
                inputElement.type = 'text';
                inputElement.value = displayValue;
            }
            
            // Replace content with input
            this.textContent = '';
            this.appendChild(inputElement);
            
            // Focus on the input
            inputElement.focus();
            
            // Handle blur event (save on focus out)
            inputElement.addEventListener('blur', function() {
                saveChanges(id, field, this.value, $(this).parent()[0]);
            });
            
            // Handle enter key
            inputElement.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && type !== 'textarea') {
                    e.preventDefault();
                    this.blur();
                }
            });
        });
        
        // Function to save changes
        function saveChanges(id, field, value, element) {
            // Send AJAX request
            fetch('/tokoh/update-field', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    id: id,
                    field: field,
                    value: value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update display
                    element.classList.remove('editing');
                    element.textContent = value || '-';
                    
                    // Add highlight effect
                    element.classList.add('highlight');
                    setTimeout(() => {
                        element.classList.remove('highlight');
                    }, 2000);
                    
                    // Show success notification
                    const notification = document.createElement('div');
                    notification.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
                    notification.style.zIndex = '1050';
                    notification.innerHTML = `
                        <strong>Berhasil!</strong> Data telah diperbarui.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.body.appendChild(notification);
                    
                    // Auto remove after 3 seconds
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                } else {
                    // Show error and revert
                    alert('Gagal menyimpan perubahan: ' + (data.message || 'Terjadi kesalahan'));
                    element.classList.remove('editing');
                    element.textContent = element.getAttribute('data-original-value') || '-';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menyimpan perubahan');
                element.classList.remove('editing');
                element.textContent = element.getAttribute('data-original-value') || '-';
            });
        }
    });
</script>
@endsection 