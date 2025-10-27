<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة العملاء المتقدمة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .main-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 25px 25px;
        }
        
        .stats-cards {
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .search-filters {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .customer-table {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .balance-positive {
            color: #28a745;
            font-weight: bold;
        }
        
        .balance-negative {
            color: #dc3545;
            font-weight: bold;
        }
        
        .balance-zero {
            color: #6c757d;
            font-weight: bold;
        }
        
        .btn-action {
            border-radius: 20px;
            padding: 0.4rem 0.8rem;
            margin: 0.1rem;
            font-size: 0.85rem;
        }
        
        .modal-financial-summary {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .financial-stat {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            margin: 0.5rem;
        }
        
        .financial-stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1976d2;
        }
        
        .financial-stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .shipment-status {
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .payment-status {
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
        }
        
        .activity-timeline {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .timeline-item {
            border-left: 3px solid #e3f2fd;
            padding-left: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #1976d2;
        }
        
        .datatables-wrapper {
            font-size: 0.9rem;
        }
        
        .table-actions {
            white-space: nowrap;
        }
        
        .customer-name-cell {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .customer-phone-cell {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2 mb-0">جاري تحميل البيانات...</p>
        </div>
    </div>

    <!-- Main Header -->
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-users-cog"></i> إدارة العملاء المتقدمة</h1>
                    <p class="mb-0">نظام شامل لإدارة العملاء والأرصدة والمعاملات المالية</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php?page=new_customer" class="btn btn-light btn-lg">
                        <i class="fas fa-user-plus"></i> إضافة عميل جديد
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="row" id="statsCardsContainer">
                <!-- Stats will be loaded here -->
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary text-white">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="mb-1" id="totalCustomers">-</h4>
                        <p class="mb-0 text-muted">إجمالي العملاء</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-success text-white">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <h4 class="mb-1 text-success" id="totalPositiveBalance">-</h4>
                        <p class="mb-0 text-muted">رصيد موجب</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger text-white">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <h4 class="mb-1 text-danger" id="totalNegativeBalance">-</h4>
                        <p class="mb-0 text-muted">رصيد سالب</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-info text-white">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <h4 class="mb-1 text-info" id="totalShipments">-</h4>
                        <p class="mb-0 text-muted">إجمالي الشحنات</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filters">
            <h5 class="mb-3"><i class="fas fa-search"></i> البحث والفلترة المتقدمة</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="customerSearch" class="form-label">البحث:</label>
                    <input type="text" id="customerSearch" class="form-control" placeholder="اسم العميل أو رقم الهاتف">
                </div>
                <div class="col-md-2 mb-3">
                    <label for="governorateFilter" class="form-label">المحافظة:</label>
                    <select id="governorateFilter" class="form-select">
                        <option value="">كل المحافظات</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="balanceFilter" class="form-label">حالة الرصيد:</label>
                    <select id="balanceFilter" class="form-select">
                        <option value="">الكل</option>
                        <option value="positive">موجب</option>
                        <option value="negative">سالب</option>
                        <option value="zero">صفر</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="statusFilter" class="form-label">حالة العميل:</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">الكل</option>
                        <option value="1">نشط</option>
                        <option value="0">موقوف</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-primary me-2" id="applyFiltersBtn">
                        <i class="fas fa-filter"></i> تطبيق الفلتر
                    </button>
                    <button type="button" class="btn btn-secondary" id="resetFiltersBtn">
                        <i class="fas fa-refresh"></i> إعادة ضبط
                    </button>
                </div>
            </div>
        </div>

        <!-- Customer Table -->
        <div class="customer-table">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5><i class="fas fa-table"></i> قائمة العملاء</h5>
                <div>
                    <button class="btn btn-outline-success btn-sm" id="exportExcelBtn">
                        <i class="fas fa-file-excel"></i> تصدير Excel
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="printBtn">
                        <i class="fas fa-print"></i> طباعة
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="customersTable">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>العميل</th>
                            <th>المحافظة/المنطقة</th>
                            <th>الرصيد الحالي</th>
                            <th>إجمالي الشحنات</th>
                            <th>المدفوعات المستحقة</th>
                            <th>آخر نشاط</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Customer Details Modal -->
    <div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-labelledby="customerDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="customerDetailsModalLabel">
                        <i class="fas fa-user-circle"></i> تفاصيل العميل
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="customerDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Financials Modal -->
    <div class="modal fade" id="customerFinancialsModal" tabindex="-1" aria-labelledby="customerFinancialsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="customerFinancialsModalLabel">
                        <i class="fas fa-chart-line"></i> الوضع المالي للعميل
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="customerFinancialsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Shipments Modal -->
    <div class="modal fade" id="customerShipmentsModal" tabindex="-1" aria-labelledby="customerShipmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="customerShipmentsModalLabel">
                        <i class="fas fa-shipping-fast"></i> شحنات العميل
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="customerShipmentsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="paymentModalLabel">
                        <i class="fas fa-money-bill-wave"></i> تسجيل دفعة جديدة
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <input type="hidden" id="paymentCustomerId" name="customer_id">
                        <div class="mb-3">
                            <label for="paymentAmount" class="form-label">مبلغ الدفعة:</label>
                            <input type="number" class="form-control" id="paymentAmount" name="amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="paymentType" class="form-label">نوع الدفعة:</label>
                            <select class="form-select" id="paymentType" name="payment_type" required>
                                <option value="cash">نقدي</option>
                                <option value="bank_transfer">تحويل بنكي</option>
                                <option value="check">شيك</option>
                                <option value="credit">إضافة رصيد</option>
                                <option value="debit">خصم من الرصيد</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="paymentNotes" class="form-label">ملاحظات:</label>
                            <textarea class="form-control" id="paymentNotes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-success" id="savePaymentBtn">
                        <i class="fas fa-save"></i> حفظ الدفعة
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    $(document).ready(function() {
        let customersTable;
        let currentCustomerId = null;
        
        // Initialize DataTable
        customersTable = $('#customersTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'customer_data_enhanced_ajax.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'get_customers_enhanced';
                    d.search_query = $('#customerSearch').val();
                    d.governorate_id = $('#governorateFilter').val();
                    d.balance_filter = $('#balanceFilter').val();
                    d.status_filter = $('#statusFilter').val();
                }
            },
            columns: [
                { data: 'id', className: 'text-center' },
                { 
                    data: null,
                    render: function(data, type, row) {
                        return `
                            <div class="customer-name-cell">${row.name}</div>
                            <div class="customer-phone-cell">
                                <i class="fas fa-phone"></i> ${row.phone}
                                ${row.email ? '<br><i class="fas fa-envelope"></i> ' + row.email : ''}
                            </div>
                        `;
                    }
                },
                { 
                    data: null,
                    render: function(data, type, row) {
                        return `
                            <div><strong>${row.governorate_name || 'غير محدد'}</strong></div>
                            <small class="text-muted">${row.area_name || 'غير محدد'}</small>
                        `;
                    }
                },
                { 
                    data: 'balance',
                    className: 'text-center',
                    render: function(data, type, row) {
                        const balance = parseFloat(data || 0);
                        let className = 'balance-zero';
                        let icon = 'fas fa-equals';
                        
                        if (balance > 0) {
                            className = 'balance-positive';
                            icon = 'fas fa-arrow-up';
                        } else if (balance < 0) {
                            className = 'balance-negative';
                            icon = 'fas fa-arrow-down';
                        }
                        
                        return `<span class="${className}"><i class="${icon}"></i> ${balance.toFixed(2)} جنيه</span>`;
                    }
                },
                { 
                    data: null,
                    className: 'text-center',
                    render: function(data, type, row) {
                        return `
                            <div><strong>${row.parcels_count || 0}</strong> شحنة</div>
                            <small class="text-muted">${parseFloat(row.total_net_amount || 0).toFixed(2)} جنيه</small>
                        `;
                    }
                },
                { 
                    data: 'pending_payments',
                    className: 'text-center',
                    render: function(data, type, row) {
                        const pending = parseFloat(data || 0);
                        if (pending > 0) {
                            return `<span class="text-danger"><strong>${pending.toFixed(2)} جنيه</strong></span>`;
                        }
                        return '<span class="text-success">لا توجد</span>';
                    }
                },
                { 
                    data: 'last_activity',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data) {
                            return new Date(data).toLocaleDateString('ar-EG');
                        }
                        return '<span class="text-muted">لا يوجد</span>';
                    }
                },
                { 
                    data: 'status',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return data == 1 
                            ? '<span class="badge bg-success">نشط</span>' 
                            : '<span class="badge bg-secondary">موقوف</span>';
                    }
                },
                { 
                    data: null,
                    className: 'table-actions text-center',
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                            <div class="btn-group" role="group">
                                <button class="btn btn-info btn-action view-details-btn" data-id="${row.id}" title="التفاصيل">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-success btn-action view-financials-btn" data-id="${row.id}" title="الوضع المالي">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                                <button class="btn btn-primary btn-action view-shipments-btn" data-id="${row.id}" title="الشحنات">
                                    <i class="fas fa-shipping-fast"></i>
                                </button>
                                <button class="btn btn-warning btn-action add-payment-btn" data-id="${row.id}" title="إضافة دفعة">
                                    <i class="fas fa-money-bill-wave"></i>
                                </button>
                                <a href="index.php?page=edit_customer&id=${row.id}" class="btn btn-secondary btn-action" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        `;
                    }
                }
            ],
            language: {
                url: './js/Arabic.json'
            },
            pageLength: 25,
            order: [[0, 'desc']],
            responsive: true,
            drawCallback: function() {
                // Re-bind event handlers after table redraw
                bindEventHandlers();
            }
        });
        
        // Load initial data
        loadStatistics();
        loadGovernorates();
        
        // Filter functionality
        $('#applyFiltersBtn').click(function() {
            customersTable.ajax.reload();
        });
        
        $('#resetFiltersBtn').click(function() {
            $('#customerSearch').val('');
            $('#governorateFilter').val('');
            $('#balanceFilter').val('');
            $('#statusFilter').val('');
            customersTable.ajax.reload();
        });
        
        // Real-time search
        $('#customerSearch').on('keyup', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(function() {
                customersTable.ajax.reload();
            }, 500);
        });
        
        // Filter change events
        $('#governorateFilter, #balanceFilter, #statusFilter').change(function() {
            customersTable.ajax.reload();
        });
        
        function bindEventHandlers() {
            // View customer details
            $('.view-details-btn').off('click').on('click', function() {
                const customerId = $(this).data('id');
                showLoading();
                loadCustomerDetails(customerId);
            });
            
            // View customer financials
            $('.view-financials-btn').off('click').on('click', function() {
                const customerId = $(this).data('id');
                showLoading();
                loadCustomerFinancials(customerId);
            });
            
            // View customer shipments
            $('.view-shipments-btn').off('click').on('click', function() {
                const customerId = $(this).data('id');
                showLoading();
                loadCustomerShipments(customerId);
            });
            
            // Add payment
            $('.add-payment-btn').off('click').on('click', function() {
                const customerId = $(this).data('id');
                openPaymentModal(customerId);
            });
        }
        
        function showLoading() {
            $('#loadingOverlay').css('display', 'flex');
        }
        
        function hideLoading() {
            $('#loadingOverlay').hide();
        }
        
        function loadStatistics() {
            $.ajax({
                url: 'customer_data_enhanced_ajax.php',
                method: 'POST',
                data: { action: 'get_customer_statistics' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const stats = response.data;
                        $('#totalCustomers').text(stats.total_customers || 0);
                        $('#totalPositiveBalance').text((stats.total_positive_balance || 0).toFixed(2) + ' جنيه');
                        $('#totalNegativeBalance').text((stats.total_negative_balance || 0).toFixed(2) + ' جنيه');
                        $('#totalShipments').text(stats.total_shipments || 0);
                    }
                }
            });
        }
        
        function loadGovernorates() {
            $.ajax({
                url: 'customer_data_enhanced_ajax.php',
                method: 'POST',
                data: { action: 'get_governorates' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const select = $('#governorateFilter');
                        response.data.forEach(gov => {
                            select.append(`<option value="${gov.id}">${gov.name}</option>`);
                        });
                    }
                }
            });
        }
        
        function loadCustomerDetails(customerId) {
            $.ajax({
                url: 'customer_data_enhanced_ajax.php',
                method: 'POST',
                data: { 
                    action: 'get_customer_full_details',
                    customer_id: customerId 
                },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.status === 'success') {
                        renderCustomerDetails(response.data);
                        $('#customerDetailsModal').modal('show');
                    } else {
                        alert('خطأ في تحميل بيانات العميل: ' + response.message);
                    }
                },
                error: function() {
                    hideLoading();
                    alert('خطأ في الاتصال بالخادم');
                }
            });
        }
        
        function loadCustomerFinancials(customerId) {
            $.ajax({
                url: 'customer_data_enhanced_ajax.php',
                method: 'POST',
                data: { 
                    action: 'get_customer_financial_report',
                    customer_id: customerId 
                },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.status === 'success') {
                        renderCustomerFinancials(response.data);
                        $('#customerFinancialsModal').modal('show');
                    } else {
                        alert('خطأ في تحميل البيانات المالية: ' + response.message);
                    }
                },
                error: function() {
                    hideLoading();
                    alert('خطأ في الاتصال بالخادم');
                }
            });
        }
        
        function loadCustomerShipments(customerId) {
            $.ajax({
                url: 'customer_data_enhanced_ajax.php',
                method: 'POST',
                data: { 
                    action: 'get_customer_shipments_detailed',
                    customer_id: customerId 
                },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.status === 'success') {
                        renderCustomerShipments(response.data);
                        $('#customerShipmentsModal').modal('show');
                    } else {
                        alert('خطأ في تحميل بيانات الشحنات: ' + response.message);
                    }
                },
                error: function() {
                    hideLoading();
                    alert('خطأ في الاتصال بالخادم');
                }
            });
        }
        
        function renderCustomerDetails(data) {
            const customer = data.customer;
            const summary = data.summary;
            const recentActivity = data.recent_activity;
            
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6><i class="fas fa-user"></i> معلومات العميل</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>الاسم:</strong> ${customer.name}</p>
                                <p><strong>الهاتف:</strong> ${customer.phone}</p>
                                <p><strong>البريد الإلكتروني:</strong> ${customer.email || 'غير محدد'}</p>
                                <p><strong>العنوان:</strong> ${customer.address || 'غير محدد'}</p>
                                <p><strong>المحافظة:</strong> ${customer.governorate_name || 'غير محدد'}</p>
                                <p><strong>المنطقة:</strong> ${customer.area_name || 'غير محدد'}</p>
                                <p><strong>تاريخ التسجيل:</strong> ${new Date(customer.date_created).toLocaleDateString('ar-EG')}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="modal-financial-summary">
                            <h6><i class="fas fa-chart-pie"></i> الملخص المالي</h6>
                            <div class="row">
                                <div class="col-6">
                                    <div class="financial-stat">
                                        <div class="financial-stat-value">${(summary.current_balance || 0).toFixed(2)}</div>
                                        <div class="financial-stat-label">الرصيد الحالي (جنيه)</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="financial-stat">
                                        <div class="financial-stat-value">${summary.total_transactions || 0}</div>
                                        <div class="financial-stat-label">إجمالي المعاملات</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="financial-stat">
                                        <div class="financial-stat-value text-success">${(summary.total_credit || 0).toFixed(2)}</div>
                                        <div class="financial-stat-label">إجمالي الائتمان</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="financial-stat">
                                        <div class="financial-stat-value text-danger">${(summary.total_debit || 0).toFixed(2)}</div>
                                        <div class="financial-stat-label">إجمالي الخصم</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6><i class="fas fa-clock"></i> النشاط الأخير</h6>
                            </div>
                            <div class="card-body">
                                <div class="activity-timeline">
            `;
            
            if (recentActivity && recentActivity.length > 0) {
                recentActivity.forEach(activity => {
                    html += `
                        <div class="timeline-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>${activity.action}</strong><br>
                                    <small class="text-muted">${activity.description}</small>
                                </div>
                                <small class="text-muted">${new Date(activity.created_at).toLocaleDateString('ar-EG')}</small>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += '<p class="text-muted text-center">لا يوجد نشاط حديث</p>';
            }
            
            html += `
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#customerDetailsContent').html(html);
        }
        
        function renderCustomerFinancials(data) {
            const summary = data.financial_summary;
            const transactions = data.balance_transactions;
            const payments = data.recent_payments;
            
            let html = `
                <div class="modal-financial-summary mb-4">
                    <h6><i class="fas fa-chart-line"></i> الملخص المالي الشامل</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="financial-stat">
                                <div class="financial-stat-value">${(summary.current_balance || 0).toFixed(2)}</div>
                                <div class="financial-stat-label">الرصيد الحالي (جنيه)</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="financial-stat">
                                <div class="financial-stat-value text-success">${(summary.total_credit || 0).toFixed(2)}</div>
                                <div class="financial-stat-label">إجمالي الائتمان</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="financial-stat">
                                <div class="financial-stat-value text-danger">${(summary.total_debit || 0).toFixed(2)}</div>
                                <div class="financial-stat-label">إجمالي الخصم</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="financial-stat">
                                <div class="financial-stat-value">${summary.total_transactions || 0}</div>
                                <div class="financial-stat-label">عدد المعاملات</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6><i class="fas fa-exchange-alt"></i> آخر المعاملات المالية</h6>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>النوع</th>
                                                <th>المبلغ</th>
                                                <th>التاريخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
            `;
            
            if (transactions && transactions.length > 0) {
                transactions.forEach(transaction => {
                    const typeClass = transaction.type === 'credit' ? 'text-success' : 'text-danger';
                    const typeIcon = transaction.type === 'credit' ? 'fa-arrow-up' : 'fa-arrow-down';
                    html += `
                        <tr>
                            <td class="${typeClass}">
                                <i class="fas ${typeIcon}"></i> ${transaction.type === 'credit' ? 'إيداع' : 'سحب'}
                            </td>
                            <td>${parseFloat(transaction.amount).toFixed(2)} جنيه</td>
                            <td>${new Date(transaction.created_at).toLocaleDateString('ar-EG')}</td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="3" class="text-center text-muted">لا توجد معاملات</td></tr>';
            }
            
            html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6><i class="fas fa-money-bill-wave"></i> آخر الدفعات</h6>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>المبلغ</th>
                                                <th>النوع</th>
                                                <th>التاريخ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
            `;
            
            if (payments && payments.length > 0) {
                payments.forEach(payment => {
                    html += `
                        <tr>
                            <td>${parseFloat(payment.amount).toFixed(2)} جنيه</td>
                            <td><span class="badge bg-info">${payment.payment_method || 'نقدي'}</span></td>
                            <td>${new Date(payment.created_at).toLocaleDateString('ar-EG')}</td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="3" class="text-center text-muted">لا توجد دفعات</td></tr>';
            }
            
            html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#customerFinancialsContent').html(html);
        }
        
        function renderCustomerShipments(data) {
            const shipments = data.shipments;
            const summary = data.summary;
            
            let html = `
                <div class="modal-financial-summary mb-4">
                    <h6><i class="fas fa-shipping-fast"></i> ملخص الشحنات</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="financial-stat">
                                <div class="financial-stat-value">${summary.total_shipments || 0}</div>
                                <div class="financial-stat-label">إجمالي الشحنات</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="financial-stat">
                                <div class="financial-stat-value text-success">${summary.delivered_shipments || 0}</div>
                                <div class="financial-stat-label">تم التسليم</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="financial-stat">
                                <div class="financial-stat-value text-warning">${summary.pending_shipments || 0}</div>
                                <div class="financial-stat-label">قيد التسليم</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="financial-stat">
                                <div class="financial-stat-value">${(summary.total_cod_amount || 0).toFixed(2)}</div>
                                <div class="financial-stat-label">إجمالي COD (جنيه)</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>رقم التتبع</th>
                                <th>المستلم</th>
                                <th>الحالة</th>
                                <th>مبلغ COD</th>
                                <th>تكلفة الشحن</th>
                                <th>حالة الدفع</th>
                                <th>التاريخ</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (shipments && shipments.length > 0) {
                shipments.forEach(shipment => {
                    let statusBadge = `<span class="shipment-status" style="background-color: ${shipment.status_color || '#6c757d'}; color: white;">
                        ${shipment.status_name || 'غير محدد'}
                    </span>`;
                    
                    let paymentBadge = '';
                    if (shipment.payment_status === 'paid') {
                        paymentBadge = '<span class="payment-status bg-success text-white">مدفوع</span>';
                    } else if (shipment.payment_status === 'partial_paid') {
                        paymentBadge = '<span class="payment-status bg-warning text-white">جزئي</span>';
                    } else {
                        paymentBadge = '<span class="payment-status bg-danger text-white">غير مدفوع</span>';
                    }
                    
                    html += `
                        <tr>
                            <td><strong>${shipment.tracking_number}</strong></td>
                            <td>
                                <strong>${shipment.recipient_name}</strong><br>
                                <small class="text-muted">${shipment.recipient_phone}</small>
                            </td>
                            <td>${statusBadge}</td>
                            <td>${parseFloat(shipment.cod_amount || 0).toFixed(2)} جنيه</td>
                            <td>${parseFloat(shipment.shipping_fees || 0).toFixed(2)} جنيه</td>
                            <td>${paymentBadge}</td>
                            <td>${new Date(shipment.date_created).toLocaleDateString('ar-EG')}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="print_waybill.php?id=${shipment.id}" target="_blank" class="btn btn-info" title="طباعة">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="index.php?page=new_parcel&id=${shipment.id}" class="btn btn-warning" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="8" class="text-center text-muted">لا توجد شحنات</td></tr>';
            }
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            $('#customerShipmentsContent').html(html);
        }
        
        function openPaymentModal(customerId) {
            currentCustomerId = customerId;
            $('#paymentCustomerId').val(customerId);
            $('#paymentForm')[0].reset();
            $('#paymentModal').modal('show');
        }
        
        // Save payment
        $('#savePaymentBtn').click(function() {
            const formData = new FormData($('#paymentForm')[0]);
            formData.append('action', 'add_customer_payment');
            
            $.ajax({
                url: 'customer_data_enhanced_ajax.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert('تم تسجيل الدفعة بنجاح!');
                        $('#paymentModal').modal('hide');
                        customersTable.ajax.reload();
                        loadStatistics();
                    } else {
                        alert('خطأ: ' + response.message);
                    }
                },
                error: function() {
                    alert('حدث خطأ أثناء تسجيل الدفعة');
                }
            });
        });
        
        // Export functionality
        $('#exportExcelBtn').click(function() {
            window.location.href = 'customer_data_enhanced_ajax.php?action=export_excel';
        });
        
        $('#printBtn').click(function() {
            window.open('customer_data_enhanced_ajax.php?action=print_report', '_blank');
        });
    });
    </script>
</body>
</html>
