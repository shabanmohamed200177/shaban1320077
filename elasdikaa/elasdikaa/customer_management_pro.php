<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة العملاء المتقدمة - شركة الأصدقاء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .search-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .customer-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            border-right: 5px solid #667eea;
        }
        
        .customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .customer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .customer-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .customer-balance {
            font-size: 1.1rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .balance-positive {
            background: linear-gradient(45deg, #56ab2f, #a8e6cf);
            color: white;
        }
        
        .balance-negative {
            background: linear-gradient(45deg, #ff416c, #ff4757);
            color: white;
        }
        
        .balance-zero {
            background: #95a5a6;
            color: white;
        }
        
        .customer-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-custom {
            border-radius: 25px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .shipments-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .search-results {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .shipment-row {
            transition: all 0.3s ease;
        }
        
        .shipment-row:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .payment-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 10px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-users"></i> إدارة العملاء المتقدمة</h1>
                    <p class="mb-0">نظام شامل لإدارة العملاء وشحناتهم وأرصدتهم</p>
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
        <!-- Search Card -->
        <div class="search-card">
            <h4 class="mb-3"><i class="fas fa-search"></i> البحث والفلترة المتقدمة</h4>
            <div class="row">
                <div class="col-md-4">
                    <label for="customerSearch" class="form-label">البحث بالاسم أو الهاتف:</label>
                    <input type="text" id="customerSearch" class="form-control" placeholder="اكتب للبحث...">
                </div>
                <div class="col-md-3">
                    <label for="governorateFilter" class="form-label">المحافظة:</label>
                    <select id="governorateFilter" class="form-select">
                        <option value="">كل المحافظات</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="balanceFilter" class="form-label">حالة الرصيد:</label>
                    <select id="balanceFilter" class="form-select">
                        <option value="">الكل</option>
                        <option value="positive">رصيد موجب</option>
                        <option value="negative">رصيد سالب</option>
                        <option value="zero">رصيد صفر</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" id="searchBtn">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2">جاري تحميل بيانات العملاء...</p>
        </div>

        <!-- Search Results -->
        <div class="search-results" id="searchResults">
            <!-- Customer cards will be loaded here -->
        </div>
    </div>

    <!-- Customer Shipments Modal -->
    <div class="modal fade" id="customerShipmentsModal" tabindex="-1" aria-labelledby="customerShipmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerShipmentsModalLabel">
                        <i class="fas fa-shipping-fast"></i> إدارة شحنات العميل
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Customer Summary -->
                    <div id="customerSummary" class="mb-4"></div>
                    
                    <!-- Shipment Search -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="shipmentSearch" class="form-label">البحث برقم الشحنة أو اسم المستلم:</label>
                            <input type="text" id="shipmentSearch" class="form-control" placeholder="ابحث في الشحنات...">
                        </div>
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label">حالة الشحنة:</label>
                            <select id="statusFilter" class="form-select">
                                <option value="">كل الحالات</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="paymentStatusFilter" class="form-label">حالة الدفع:</label>
                            <select id="paymentStatusFilter" class="form-select">
                                <option value="">كل حالات الدفع</option>
                                <option value="paid">مدفوع</option>
                                <option value="partial_paid">دفع جزئي</option>
                                <option value="unpaid">غير مدفوع</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-primary w-100" id="filterShipmentsBtn">
                                <i class="fas fa-filter"></i> فلترة
                            </button>
                        </div>
                    </div>
                    
                    <!-- Shipments Table -->
                    <div class="shipments-table">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="shipmentsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>رقم التتبع</th>
                                        <th>المستلم</th>
                                        <th>الحالة</th>
                                        <th>مبلغ COD</th>
                                        <th>تكلفة الشحن</th>
                                        <th>المدفوع</th>
                                        <th>المتبقي</th>
                                        <th>حالة الدفع</th>
                                        <th>التاريخ</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody id="shipmentsTableBody">
                                    <!-- Shipments will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-success" id="processPaymentsBtn">
                        <i class="fas fa-money-bill-wave"></i> معالجة الدفعات المحددة
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">
                        <i class="fas fa-dollar-sign"></i> تسجيل دفعة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <input type="hidden" id="parcelId" name="parcel_id">
                        <div class="mb-3">
                            <label for="paymentAmount" class="form-label">المبلغ المدفوع:</label>
                            <input type="number" class="form-control" id="paymentAmount" name="amount" step="0.01" min="0" required>
                            <div class="form-text" id="paymentInfo"></div>
                        </div>
                        <div class="mb-3">
                            <label for="paymentNotes" class="form-label">ملاحظات (اختياري):</label>
                            <textarea class="form-control" id="paymentNotes" name="notes" rows="2"></textarea>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        let currentCustomerId = null;
        let allShipments = [];
        let filteredShipments = [];
        
        // Load governorates
        loadGovernorates();
        
        // Load all customers initially
        loadCustomers();
        
        // Search functionality
        $('#searchBtn').click(function() {
            loadCustomers();
        });
        
        // Real-time search
        $('#customerSearch').on('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(function() {
                loadCustomers();
            }, 500);
        });
        
        function loadGovernorates() {
            $.ajax({
                url: 'ajax.php?action=get_all_governorates',
                method: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 1 || Array.isArray(response)) {
                        const select = $('#governorateFilter');
                        let data = response.data || response;
                        data.forEach(gov => {
                            select.append(`<option value="${gov.id}">${gov.name}</option>`);
                        });
                    }
                }
            });
        }
        
        function loadCustomers() {
            $('#loadingSpinner').show();
            $('#searchResults').hide();
            
            const searchTerm = $('#customerSearch').val();
            const governorateId = $('#governorateFilter').val();
            const balanceFilter = $('#balanceFilter').val();
            
            $.ajax({
                url: 'customer_data_ajax.php',
                method: 'POST',
                data: {
                    action: 'get_customers_list',
                    search_query: searchTerm,
                    governorate_id: governorateId,
                    balance_filter: balanceFilter
                },
                dataType: 'json',
                success: function(response) {
                    $('#loadingSpinner').hide();
                    $('#searchResults').show();
                    
                    if (response.status === 'success' && response.data && response.data.length > 0) {
                        displayCustomers(response.data);
                    } else {
                        $('#searchResults').html(`
                            <div class="no-results">
                                <i class="fas fa-search fa-3x mb-3"></i>
                                <h4>لا توجد نتائج</h4>
                                <p>لم يتم العثور على عملاء مطابقين لمعايير البحث</p>
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#loadingSpinner').hide();
                    $('#searchResults').html(`
                        <div class="no-results">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                            <h4>خطأ في التحميل</h4>
                            <p>حدث خطأ أثناء تحميل بيانات العملاء</p>
                        </div>
                    `);
                }
            });
        }
        
        function displayCustomers(customers) {
            let html = '';
            
            customers.forEach(customer => {
                const balance = parseFloat(customer.balance || 0);
                let balanceClass = 'balance-zero';
                let balanceIcon = 'fas fa-equals';
                
                if (balance > 0) {
                    balanceClass = 'balance-positive';
                    balanceIcon = 'fas fa-arrow-up';
                } else if (balance < 0) {
                    balanceClass = 'balance-negative';
                    balanceIcon = 'fas fa-arrow-down';
                }
                
                html += `
                    <div class="customer-card">
                        <div class="customer-header">
                            <div>
                                <div class="customer-name">
                                    <i class="fas fa-user"></i> ${customer.name}
                                </div>
                                <div class="text-muted">
                                    <i class="fas fa-phone"></i> ${customer.phone}
                                    ${customer.email ? `<br><i class="fas fa-envelope"></i> ${customer.email}` : ''}
                                </div>
                            </div>
                            <div class="customer-balance ${balanceClass}">
                                <i class="${balanceIcon}"></i> ${balance.toFixed(2)} جنيه
                            </div>
                        </div>
                        
                        <div class="customer-stats">
                            <div class="stat-item">
                                <div class="stat-value">${customer.parcels_count || 0}</div>
                                <div class="stat-label">إجمالي الشحنات</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${parseFloat(customer.total_net_amount || 0).toFixed(2)}</div>
                                <div class="stat-label">صافي المبلغ</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${customer.governorate_name || 'غير محدد'}</div>
                                <div class="stat-label">المحافظة</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${customer.area_name || 'غير محدد'}</div>
                                <div class="stat-label">المنطقة</div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn btn-primary btn-custom view-shipments-btn" data-customer-id="${customer.id}" data-customer-name="${customer.name}">
                                <i class="fas fa-shipping-fast"></i> إدارة الشحنات والدفعات
                            </button>
                            <a href="index.php?page=edit_customer&id=${customer.id}" class="btn btn-warning btn-custom">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                            <a href="index.php?page=customer_details&id=${customer.id}" class="btn btn-info btn-custom">
                                <i class="fas fa-eye"></i> التفاصيل
                            </a>
                        </div>
                    </div>
                `;
            });
            
            $('#searchResults').html(html);
        }
        
        // View customer shipments
        $(document).on('click', '.view-shipments-btn', function() {
            const customerId = $(this).data('customer-id');
            const customerName = $(this).data('customer-name');
            
            currentCustomerId = customerId;
            $('#customerShipmentsModalLabel').html(`<i class="fas fa-shipping-fast"></i> إدارة شحنات العميل: ${customerName}`);
            
            loadCustomerShipments(customerId);
            $('#customerShipmentsModal').modal('show');
        });
        
        function loadCustomerShipments(customerId) {
            $.ajax({
                url: 'system_integration_fixes.php',
                method: 'POST',
                data: {
                    action: 'get_customer_shipments',
                    customer_id: customerId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        allShipments = response.data;
                        filteredShipments = allShipments;
                        displayShipments();
                        loadCustomerSummary(customerId);
                    }
                }
            });
        }
        
        function loadCustomerSummary(customerId) {
            // Load customer financial summary
            $.ajax({
                url: 'ajax.php?action=get_customer_financial_summary',
                method: 'POST',
                data: { customer_id: customerId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const summary = response.data;
                        $('#customerSummary').html(`
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="stat-item">
                                        <div class="stat-value text-primary">${summary.total_transactions || 0}</div>
                                        <div class="stat-label">إجمالي المعاملات</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-item">
                                        <div class="stat-value text-success">${parseFloat(summary.total_credit || 0).toFixed(2)}</div>
                                        <div class="stat-label">إجمالي الائتمان</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-item">
                                        <div class="stat-value text-danger">${parseFloat(summary.total_debit || 0).toFixed(2)}</div>
                                        <div class="stat-label">إجمالي الخصم</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-item">
                                        <div class="stat-value text-info">${parseFloat(summary.current_balance || 0).toFixed(2)}</div>
                                        <div class="stat-label">الرصيد الحالي</div>
                                    </div>
                                </div>
                            </div>
                        `);
                    }
                }
            });
        }
        
        function displayShipments() {
            let html = '';
            
            if (filteredShipments.length === 0) {
                html = '<tr><td colspan="10" class="text-center py-4">لا توجد شحنات</td></tr>';
            } else {
                filteredShipments.forEach(shipment => {
                    const codAmount = parseFloat(shipment.cod_amount || 0);
                    const shippingFees = parseFloat(shipment.shipping_fees || 0);
                    const paidAmount = parseFloat(shipment.paid_amount || 0);
                    
                    let totalToCollect = codAmount;
                    if (shipment.shipping_payer === 'recipient') {
                        totalToCollect += shippingFees;
                    }
                    
                    const remaining = totalToCollect - paidAmount;
                    
                    let statusBadge = `<span class="status-badge" style="background-color: ${shipment.status_color || '#6c757d'}; color: white;">
                        ${shipment.status_name || 'غير محدد'}
                    </span>`;
                    
                    let paymentBadge = '';
                    if (shipment.payment_status === 'paid') {
                        paymentBadge = '<span class="payment-badge bg-success text-white">مدفوع</span>';
                    } else if (shipment.payment_status === 'partial_paid') {
                        paymentBadge = '<span class="payment-badge bg-warning text-white">جزئي</span>';
                    } else {
                        paymentBadge = '<span class="payment-badge bg-danger text-white">غير مدفوع</span>';
                    }
                    
                    html += `
                        <tr class="shipment-row">
                            <td><strong>${shipment.tracking_number}</strong></td>
                            <td>
                                <strong>${shipment.recipient_name}</strong><br>
                                <small class="text-muted">${shipment.recipient_phone}</small>
                            </td>
                            <td>${statusBadge}</td>
                            <td>${codAmount.toFixed(2)} جنيه</td>
                            <td>${shippingFees.toFixed(2)} جنيه</td>
                            <td>${paidAmount.toFixed(2)} جنيه</td>
                            <td class="${remaining > 0 ? 'text-danger' : 'text-success'}">${remaining.toFixed(2)} جنيه</td>
                            <td>${paymentBadge}</td>
                            <td><small>${new Date(shipment.date_created).toLocaleDateString('ar-EG')}</small></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="print_waybill.php?id=${shipment.id}" target="_blank" class="btn btn-info" title="طباعة">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="index.php?page=new_parcel&id=${shipment.id}" class="btn btn-warning" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    ${remaining > 0 ? `
                                    <button class="btn btn-success pay-btn" 
                                            data-parcel-id="${shipment.id}"
                                            data-cod="${codAmount}"
                                            data-paid="${paidAmount}"
                                            data-total="${totalToCollect}"
                                            title="دفع">
                                        <i class="fas fa-dollar-sign"></i>
                                    </button>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            
            $('#shipmentsTableBody').html(html);
        }
        
        // Filter shipments
        $('#filterShipmentsBtn').click(function() {
            const searchTerm = $('#shipmentSearch').val().toLowerCase();
            const statusFilter = $('#statusFilter').val();
            const paymentStatusFilter = $('#paymentStatusFilter').val();
            
            filteredShipments = allShipments.filter(shipment => {
                const matchesSearch = !searchTerm || 
                    shipment.tracking_number.toLowerCase().includes(searchTerm) ||
                    shipment.recipient_name.toLowerCase().includes(searchTerm);
                
                const matchesStatus = !statusFilter || shipment.status == statusFilter;
                const matchesPayment = !paymentStatusFilter || shipment.payment_status === paymentStatusFilter;
                
                return matchesSearch && matchesStatus && matchesPayment;
            });
            
            displayShipments();
        });
        
        // Real-time shipment search
        $('#shipmentSearch').on('input', function() {
            $('#filterShipmentsBtn').click();
        });
        
        // Payment functionality
        $(document).on('click', '.pay-btn', function() {
            const parcelId = $(this).data('parcel-id');
            const codAmount = parseFloat($(this).data('cod'));
            const paidAmount = parseFloat($(this).data('paid'));
            const totalAmount = parseFloat($(this).data('total'));
            const remainingAmount = totalAmount - paidAmount;
            
            $('#parcelId').val(parcelId);
            $('#paymentAmount').attr('max', remainingAmount).val(remainingAmount);
            $('#paymentInfo').html(`
                <strong>مبلغ COD:</strong> ${codAmount.toFixed(2)} جنيه<br>
                <strong>الإجمالي المطلوب:</strong> ${totalAmount.toFixed(2)} جنيه<br>
                <strong>المدفوع سابقاً:</strong> ${paidAmount.toFixed(2)} جنيه<br>
                <strong>المتبقي:</strong> ${remainingAmount.toFixed(2)} جنيه
            `);
            
            $('#paymentModal').modal('show');
        });
        
        // Save payment
        $('#savePaymentBtn').click(function() {
            const formData = {
                action: 'partial_pay_parcel',
                id: $('#parcelId').val(),
                amount: $('#paymentAmount').val(),
                notes: $('#paymentNotes').val()
            };
            
            $.ajax({
                url: 'parcel_list.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert('تم تسجيل الدفعة بنجاح!');
                        $('#paymentModal').modal('hide');
                        loadCustomerShipments(currentCustomerId); // Reload shipments
                    } else {
                        alert('خطأ: ' + response.message);
                    }
                },
                error: function() {
                    alert('حدث خطأ أثناء تسجيل الدفعة');
                }
            });
        });
    });
    </script>
</body>
</html>