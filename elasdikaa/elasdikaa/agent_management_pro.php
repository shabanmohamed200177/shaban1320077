<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الوكلاء المتقدمة - شركة الأصدقاء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .header-section {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 30px 30px;
        }
        
        .agent-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .agent-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(45deg, #667eea, #764ba2);
        }
        
        .agent-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }
        
        .agent-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .agent-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .agent-status {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-active {
            background: linear-gradient(45deg, #56ab2f, #a8e6cf);
            color: white;
        }
        
        .status-inactive {
            background: linear-gradient(45deg, #ff416c, #ff4757);
            color: white;
        }
        
        .agent-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .info-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .info-icon {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .shipment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-title {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn-custom {
            border-radius: 25px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary-custom {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-success-custom {
            background: linear-gradient(45deg, #56ab2f, #a8e6cf);
            color: white;
        }
        
        .btn-warning-custom {
            background: linear-gradient(45deg, #f093fb, #f5576c);
            color: white;
        }
        
        .btn-info-custom {
            background: linear-gradient(45deg, #4facfe, #00f2fe);
            color: white;
        }
        
        .search-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .shipments-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .table-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .shipment-row:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 3rem;
            color: white;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: white;
        }
        
        .financial-summary {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-user-tie"></i> إدارة الوكلاء المتقدمة</h1>
                    <p class="mb-0">نظام شامل لإدارة الوكلاء والشحنات المرسلة والمستلمة</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="index.php?page=new_agent" class="btn btn-light btn-lg">
                        <i class="fas fa-user-plus"></i> إضافة وكيل جديد
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Search Card -->
        <div class="search-card">
            <h4 class="mb-3"><i class="fas fa-search"></i> البحث والفلترة</h4>
            <div class="row">
                <div class="col-md-4">
                    <label for="agentSearch" class="form-label">البحث بالاسم أو الشركة:</label>
                    <input type="text" id="agentSearch" class="form-control" placeholder="اكتب للبحث...">
                </div>
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">الحالة:</label>
                    <select id="statusFilter" class="form-select">
                        <option value="">الكل</option>
                        <option value="1">نشط</option>
                        <option value="0">غير نشط</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="governorateFilter" class="form-label">المحافظة:</label>
                    <select id="governorateFilter" class="form-select">
                        <option value="">كل المحافظات</option>
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
            <div class="spinner-border" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2">جاري تحميل بيانات الوكلاء...</p>
        </div>

        <!-- Search Results -->
        <div id="searchResults">
            <!-- Agent cards will be loaded here -->
        </div>
    </div>

    <!-- Agent Shipments Modal -->
    <div class="modal fade" id="agentShipmentsModal" tabindex="-1" aria-labelledby="agentShipmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="agentShipmentsModalLabel">
                        <i class="fas fa-shipping-fast"></i> إدارة شحنات الوكيل
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Agent Financial Summary -->
                    <div class="financial-summary" id="agentFinancialSummary">
                        <!-- Summary will be loaded here -->
                    </div>
                    
                    <!-- Navigation Tabs -->
                    <ul class="nav nav-tabs" id="shipmentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent-shipments" type="button" role="tab">
                                <i class="fas fa-paper-plane"></i> الشحنات المرسلة إليه
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="received-tab" data-bs-toggle="tab" data-bs-target="#received-shipments" type="button" role="tab">
                                <i class="fas fa-inbox"></i> الشحنات المستلمة منه
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content mt-3" id="shipmentTabContent">
                        <!-- Sent Shipments Tab -->
                        <div class="tab-pane fade show active" id="sent-shipments" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <input type="text" id="sentShipmentSearch" class="form-control" placeholder="البحث برقم الشحنة أو اسم المستلم...">
                                </div>
                                <div class="col-md-3">
                                    <select id="sentStatusFilter" class="form-select">
                                        <option value="">كل الحالات</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-primary w-100" id="filterSentBtn">
                                        <i class="fas fa-filter"></i> فلترة
                                    </button>
                                </div>
                            </div>
                            <div class="shipments-table">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-header">
                                            <tr>
                                                <th>رقم التتبع</th>
                                                <th>المرسل</th>
                                                <th>المستلم</th>
                                                <th>الحالة</th>
                                                <th>مبلغ COD</th>
                                                <th>عمولة الوكيل</th>
                                                <th>التاريخ</th>
                                                <th>الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sentShipmentsBody">
                                            <!-- Sent shipments will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Received Shipments Tab -->
                        <div class="tab-pane fade" id="received-shipments" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <input type="text" id="receivedShipmentSearch" class="form-control" placeholder="البحث برقم الشحنة أو اسم المرسل...">
                                </div>
                                <div class="col-md-3">
                                    <select id="receivedStatusFilter" class="form-select">
                                        <option value="">كل الحالات</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-primary w-100" id="filterReceivedBtn">
                                        <i class="fas fa-filter"></i> فلترة
                                    </button>
                                </div>
                            </div>
                            <div class="shipments-table">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-header">
                                            <tr>
                                                <th>رقم التتبع</th>
                                                <th>المرسل</th>
                                                <th>المستلم</th>
                                                <th>الحالة</th>
                                                <th>مبلغ COD</th>
                                                <th>عمولة الوكيل</th>
                                                <th>التاريخ</th>
                                                <th>الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="receivedShipmentsBody">
                                            <!-- Received shipments will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-success" id="generateReportBtn">
                        <i class="fas fa-file-excel"></i> تصدير التقرير
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        let currentAgentId = null;
        let allSentShipments = [];
        let allReceivedShipments = [];
        let filteredSentShipments = [];
        let filteredReceivedShipments = [];
        
        // Load governorates and initial data
        loadGovernorates();
        loadAgents();
        
        // Search functionality
        $('#searchBtn').click(function() {
            loadAgents();
        });
        
        // Real-time search
        $('#agentSearch').on('input', function() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(function() {
                loadAgents();
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
        
        function loadAgents() {
            $('#loadingSpinner').show();
            $('#searchResults').hide();
            
            const searchTerm = $('#agentSearch').val();
            const statusFilter = $('#statusFilter').val();
            const governorateId = $('#governorateFilter').val();
            
            $.ajax({
                url: 'ajax.php?action=get_all_agents',
                method: 'POST',
                data: {
                    search_query: searchTerm,
                    status_filter: statusFilter,
                    governorate_id: governorateId
                },
                dataType: 'json',
                success: function(response) {
                    $('#loadingSpinner').hide();
                    $('#searchResults').show();
                    
                    if (response.status === 1 && response.data && response.data.length > 0) {
                        displayAgents(response.data);
                    } else {
                        $('#searchResults').html(`
                            <div class="no-results">
                                <i class="fas fa-search fa-3x mb-3"></i>
                                <h4>لا توجد نتائج</h4>
                                <p>لم يتم العثور على وكلاء مطابقين لمعايير البحث</p>
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#loadingSpinner').hide();
                    $('#searchResults').html(`
                        <div class="no-results">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <h4>خطأ في التحميل</h4>
                            <p>حدث خطأ أثناء تحميل بيانات الوكلاء</p>
                        </div>
                    `);
                }
            });
        }
        
        function displayAgents(agents) {
            let html = '';
            
            agents.forEach(agent => {
                const isActive = agent.status == 1;
                const statusClass = isActive ? 'status-active' : 'status-inactive';
                const statusText = isActive ? 'نشط' : 'غير نشط';
                const statusIcon = isActive ? 'fas fa-check-circle' : 'fas fa-times-circle';
                
                html += `
                    <div class="agent-card">
                        <div class="agent-header">
                            <div class="agent-name">
                                <i class="fas fa-building"></i>
                                ${agent.company_name}
                            </div>
                            <div class="agent-status ${statusClass}">
                                <i class="${statusIcon}"></i> ${statusText}
                            </div>
                        </div>
                        
                        <div class="agent-info">
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-user"></i></div>
                                <div class="info-value">${agent.name}</div>
                                <div class="info-label">اسم المسؤول</div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-phone"></i></div>
                                <div class="info-value">${agent.phone}</div>
                                <div class="info-label">رقم الهاتف</div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-envelope"></i></div>
                                <div class="info-value">${agent.email || 'غير متوفر'}</div>
                                <div class="info-label">البريد الإلكتروني</div>
                            </div>
                            <div class="info-item">
                                <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="info-value">${agent.governorate_name || 'غير محدد'}</div>
                                <div class="info-label">المحافظة</div>
                            </div>
                        </div>
                        
                        <div class="shipment-stats" id="stats-${agent.id}">
                            <div class="stat-card">
                                <div class="stat-number" id="sent-count-${agent.id}">-</div>
                                <div class="stat-title">شحنات مرسلة إليه</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="received-count-${agent.id}">-</div>
                                <div class="stat-title">شحنات مستلمة منه</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="total-commission-${agent.id}">-</div>
                                <div class="stat-title">إجمالي العمولات</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="pending-amount-${agent.id}">-</div>
                                <div class="stat-title">المبالغ المعلقة</div>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn btn-primary-custom btn-custom view-shipments-btn" 
                                    data-agent-id="${agent.id}" 
                                    data-agent-name="${agent.company_name}">
                                <i class="fas fa-shipping-fast"></i> إدارة الشحنات
                            </button>
                            <a href="index.php?page=edit_agent&id=${agent.id}" class="btn btn-warning-custom btn-custom">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                            <button class="btn btn-info-custom btn-custom view-profile-btn" data-agent-id="${agent.id}">
                                <i class="fas fa-user"></i> الملف الشخصي
                            </button>
                            <button class="btn btn-success-custom btn-custom toggle-status-btn" 
                                    data-agent-id="${agent.id}" 
                                    data-current-status="${agent.status}">
                                <i class="fas fa-toggle-${isActive ? 'on' : 'off'}"></i> ${isActive ? 'إيقاف' : 'تفعيل'}
                            </button>
                        </div>
                    </div>
                `;
                
                // Load agent statistics after rendering
                setTimeout(() => loadAgentStats(agent.id), 100);
            });
            
            $('#searchResults').html(html);
        }
        
        function loadAgentStats(agentId) {
            // Load sent shipments count
            $.ajax({
                url: 'ajax.php?action=get_agent_shipments_count',
                method: 'POST',
                data: { agent_id: agentId, type: 'sent' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $(`#sent-count-${agentId}`).text(response.count || 0);
                    }
                }
            });
            
            // Load received shipments count
            $.ajax({
                url: 'ajax.php?action=get_agent_shipments_count',
                method: 'POST',
                data: { agent_id: agentId, type: 'received' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $(`#received-count-${agentId}`).text(response.count || 0);
                    }
                }
            });
            
            // Load financial summary
            $.ajax({
                url: 'ajax.php?action=get_agent_financial_summary',
                method: 'POST',
                data: { agent_id: agentId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const data = response.data;
                        $(`#total-commission-${agentId}`).text((parseFloat(data.total_commission || 0)).toFixed(2) + ' جنيه');
                        $(`#pending-amount-${agentId}`).text((parseFloat(data.pending_amount || 0)).toFixed(2) + ' جنيه');
                    }
                }
            });
        }
        
        // View agent shipments
        $(document).on('click', '.view-shipments-btn', function() {
            const agentId = $(this).data('agent-id');
            const agentName = $(this).data('agent-name');
            
            currentAgentId = agentId;
            $('#agentShipmentsModalLabel').html(`<i class="fas fa-shipping-fast"></i> إدارة شحنات الوكيل: ${agentName}`);
            
            loadAgentShipments(agentId);
            $('#agentShipmentsModal').modal('show');
        });
        
        function loadAgentShipments(agentId) {
            // Load sent shipments
            $.ajax({
                url: 'ajax.php?action=get_agent_shipments',
                method: 'POST',
                data: { agent_id: agentId, type: 'sent' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        allSentShipments = response.data;
                        filteredSentShipments = allSentShipments;
                        displaySentShipments();
                    }
                }
            });
            
            // Load received shipments
            $.ajax({
                url: 'ajax.php?action=get_agent_shipments',
                method: 'POST',
                data: { agent_id: agentId, type: 'received' },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        allReceivedShipments = response.data;
                        filteredReceivedShipments = allReceivedShipments;
                        displayReceivedShipments();
                    }
                }
            });
            
            // Load financial summary
            loadAgentFinancialSummary(agentId);
        }
        
        function loadAgentFinancialSummary(agentId) {
            $.ajax({
                url: 'ajax.php?action=get_agent_financial_summary',
                method: 'POST',
                data: { agent_id: agentId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const data = response.data;
                        $('#agentFinancialSummary').html(`
                            <h5 class="mb-3"><i class="fas fa-chart-line"></i> الملخص المالي للوكيل</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-number">${data.total_sent_shipments || 0}</div>
                                        <div class="stat-title">شحنات مرسلة</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-number">${data.total_received_shipments || 0}</div>
                                        <div class="stat-title">شحنات مستلمة</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-number">${parseFloat(data.total_commission || 0).toFixed(2)}</div>
                                        <div class="stat-title">إجمالي العمولات</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-card">
                                        <div class="stat-number">${parseFloat(data.pending_amount || 0).toFixed(2)}</div>
                                        <div class="stat-title">المبالغ المعلقة</div>
                                    </div>
                                </div>
                            </div>
                        `);
                    }
                }
            });
        }
        
        function displaySentShipments() {
            let html = '';
            
            if (filteredSentShipments.length === 0) {
                html = '<tr><td colspan="8" class="text-center py-4">لا توجد شحنات مرسلة</td></tr>';
            } else {
                filteredSentShipments.forEach(shipment => {
                    const statusBadge = `<span class="badge" style="background-color: ${shipment.status_color || '#6c757d'}; color: white;">
                        ${shipment.status_name || 'غير محدد'}
                    </span>`;
                    
                    html += `
                        <tr class="shipment-row">
                            <td><strong>${shipment.tracking_number}</strong></td>
                            <td>${shipment.sender_name}</td>
                            <td>${shipment.recipient_name}</td>
                            <td>${statusBadge}</td>
                            <td>${parseFloat(shipment.cod_amount || 0).toFixed(2)} جنيه</td>
                            <td>${parseFloat(shipment.agent_share || 0).toFixed(2)} جنيه</td>
                            <td><small>${new Date(shipment.date_created).toLocaleDateString('ar-EG')}</small></td>
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
            }
            
            $('#sentShipmentsBody').html(html);
        }
        
        function displayReceivedShipments() {
            let html = '';
            
            if (filteredReceivedShipments.length === 0) {
                html = '<tr><td colspan="8" class="text-center py-4">لا توجد شحنات مستلمة</td></tr>';
            } else {
                filteredReceivedShipments.forEach(shipment => {
                    const statusBadge = `<span class="badge" style="background-color: ${shipment.status_color || '#6c757d'}; color: white;">
                        ${shipment.status_name || 'غير محدد'}
                    </span>`;
                    
                    html += `
                        <tr class="shipment-row">
                            <td><strong>${shipment.tracking_number}</strong></td>
                            <td>${shipment.sender_name}</td>
                            <td>${shipment.recipient_name}</td>
                            <td>${statusBadge}</td>
                            <td>${parseFloat(shipment.cod_amount || 0).toFixed(2)} جنيه</td>
                            <td>${parseFloat(shipment.agent_share || 0).toFixed(2)} جنيه</td>
                            <td><small>${new Date(shipment.date_created).toLocaleDateString('ar-EG')}</small></td>
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
            }
            
            $('#receivedShipmentsBody').html(html);
        }
        
        // Filter functionality
        $('#filterSentBtn').click(function() {
            const searchTerm = $('#sentShipmentSearch').val().toLowerCase();
            const statusFilter = $('#sentStatusFilter').val();
            
            filteredSentShipments = allSentShipments.filter(shipment => {
                const matchesSearch = !searchTerm || 
                    shipment.tracking_number.toLowerCase().includes(searchTerm) ||
                    shipment.recipient_name.toLowerCase().includes(searchTerm);
                
                const matchesStatus = !statusFilter || shipment.status == statusFilter;
                
                return matchesSearch && matchesStatus;
            });
            
            displaySentShipments();
        });
        
        $('#filterReceivedBtn').click(function() {
            const searchTerm = $('#receivedShipmentSearch').val().toLowerCase();
            const statusFilter = $('#receivedStatusFilter').val();
            
            filteredReceivedShipments = allReceivedShipments.filter(shipment => {
                const matchesSearch = !searchTerm || 
                    shipment.tracking_number.toLowerCase().includes(searchTerm) ||
                    shipment.sender_name.toLowerCase().includes(searchTerm);
                
                const matchesStatus = !statusFilter || shipment.status == statusFilter;
                
                return matchesSearch && matchesStatus;
            });
            
            displayReceivedShipments();
        });
        
        // Real-time search
        $('#sentShipmentSearch, #receivedShipmentSearch').on('input', function() {
            if ($(this).attr('id') === 'sentShipmentSearch') {
                $('#filterSentBtn').click();
            } else {
                $('#filterReceivedBtn').click();
            }
        });
        
        // Toggle agent status
        $(document).on('click', '.toggle-status-btn', function() {
            const agentId = $(this).data('agent-id');
            const currentStatus = $(this).data('current-status');
            const newStatus = currentStatus == 1 ? 0 : 1;
            
            if (confirm('هل أنت متأكد من تغيير حالة الوكيل؟')) {
                $.ajax({
                    url: 'ajax.php?action=toggle_agent_status',
                    method: 'POST',
                    data: { agent_id: agentId, new_status: newStatus },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert('تم تغيير حالة الوكيل بنجاح');
                            loadAgents(); // Reload agents
                        } else {
                            alert('خطأ: ' + response.message);
                        }
                    }
                });
            }
        });
    });
    </script>
</body>
</html>