<?php
/**
 * Enhanced Payment Management UI
 * واجهة إدارة الدفعات المحدثة
 */

// تأكد من بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['login_id'])) {
    header("location: login.php");
    exit;
}

include 'db_connect.php';
include 'enhanced_payment_system.php';

$parcel_id = isset($_GET['parcel_id']) ? intval($_GET['parcel_id']) : 0;
$payment_system = new EnhancedPaymentSystem($conn);

// جلب البيانات المالية للشحنة إذا تم تحديد معرف
$parcel_data = null;
$financial_summary = null;
if ($parcel_id > 0) {
    $financial_summary = $payment_system->getParcelFinancialSummary($parcel_id);
    if ($financial_summary) {
        $parcel_data = $financial_summary['parcel_info'];
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>إدارة الدفعات المحدثة | <?php echo $_SESSION['system']['name'] ?? 'نظام الشحن' ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .financial-summary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .payment-status {
            border-radius: 20px;
            padding: 8px 16px;
            font-weight: bold;
            text-align: center;
        }
        
        .status-unpaid { background-color: #dc3545; color: white; }
        .status-partially-paid { background-color: #ffc107; color: #212529; }
        .status-fully-paid { background-color: #28a745; color: white; }
        
        .transaction-item {
            border-left: 4px solid #007bff;
            background: white;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .payment-item { border-left-color: #28a745; }
        .delivery-item { border-left-color: #007bff; }
        .return-item { border-left-color: #dc3545; }
        
        .amount-display {
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .amount-positive { color: #28a745; }
        .amount-negative { color: #dc3545; }
        .amount-neutral { color: #6c757d; }
        
        .action-buttons .btn {
            margin: 2px;
            border-radius: 20px;
        }
        
        .search-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .progress-container {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .custom-progress {
            height: 8px;
            border-radius: 10px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-credit-card me-2"></i>
                    إدارة الدفعات المحدثة
                </h2>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-container">
            <h5><i class="fas fa-search me-2"></i>البحث عن الشحنات</h5>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">نوع البحث:</label>
                    <select class="form-select" id="searchType">
                        <option value="all">بحث شامل</option>
                        <option value="tracking_number">رقم التتبع</option>
                        <option value="sender_phone">هاتف المرسل</option>
                        <option value="recipient_name">اسم المستلم</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">مصطلح البحث:</label>
                    <input type="text" class="form-control" id="searchTerm" placeholder="ادخل مصطلح البحث...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" onclick="searchParcels()">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </div>
            </div>
            
            <!-- Search Results -->
            <div id="searchResults" class="mt-3"></div>
        </div>

        <?php if ($parcel_data): ?>
        <!-- Financial Summary -->
        <div class="financial-summary">
            <div class="row">
                <div class="col-md-6">
                    <h4><i class="fas fa-box me-2"></i>شحنة رقم: <?php echo htmlspecialchars($parcel_data['tracking_number']); ?></h4>
                    <p class="mb-2"><strong>المستلم:</strong> <?php echo htmlspecialchars($parcel_data['recipient_name']); ?></p>
                    <p class="mb-0"><strong>هاتف المرسل:</strong> <?php echo htmlspecialchars($parcel_data['sender_phone']); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="payment-status status-<?php echo str_replace('_', '-', $parcel_data['detailed_payment_status']); ?>">
                        <?php 
                        $status_text = [
                            'unpaid' => 'غير مدفوعة',
                            'partially_paid' => 'مدفوعة جزئياً',
                            'fully_paid' => 'مدفوعة بالكامل'
                        ];
                        echo $status_text[$parcel_data['detailed_payment_status']] ?? 'غير محدد';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Indicators -->
        <div class="row">
            <div class="col-md-6">
                <div class="progress-container">
                    <h6><i class="fas fa-truck me-2"></i>نسبة التسليم</h6>
                    <div class="progress custom-progress">
                        <div class="progress-bar bg-info" style="width: <?php echo $financial_summary['summary']['delivery_percentage']; ?>%"></div>
                    </div>
                    <small class="text-muted">
                        <?php echo number_format($parcel_data['delivered_value'], 2); ?> من <?php echo number_format($parcel_data['total_shipment_value'], 2); ?> جنيه
                        (<?php echo $financial_summary['summary']['delivery_percentage']; ?>%)
                    </small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="progress-container">
                    <h6><i class="fas fa-money-bill-wave me-2"></i>نسبة الدفع</h6>
                    <div class="progress custom-progress">
                        <div class="progress-bar bg-success" style="width: <?php echo $financial_summary['summary']['payment_percentage']; ?>%"></div>
                    </div>
                    <small class="text-muted">
                        <?php echo number_format($parcel_data['amount_paid_so_far'], 2); ?> من <?php echo number_format($parcel_data['delivered_value'], 2); ?> جنيه
                        (<?php echo $financial_summary['summary']['payment_percentage']; ?>%)
                    </small>
                </div>
            </div>
        </div>

        <!-- Financial Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="amount-display amount-neutral">
                            <?php echo number_format($parcel_data['total_shipment_value'], 2); ?> ج.م
                        </h5>
                        <p class="card-text">إجمالي قيمة الشحنة</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="amount-display amount-positive">
                            <?php echo number_format($parcel_data['delivered_value'], 2); ?> ج.م
                        </h5>
                        <p class="card-text">القيمة المسلمة</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="amount-display amount-negative">
                            <?php echo number_format($parcel_data['returned_value'], 2); ?> ج.م
                        </h5>
                        <p class="card-text">القيمة المرتجعة</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="amount-display <?php echo $parcel_data['remaining_balance'] > 0 ? 'amount-negative' : 'amount-positive'; ?>">
                            <?php echo number_format($parcel_data['remaining_balance'], 2); ?> ج.م
                        </h5>
                        <p class="card-text">الرصيد المتبقي</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons mb-4 text-center">
            <button class="btn btn-success" onclick="showPaymentModal()">
                <i class="fas fa-plus-circle me-2"></i>إضافة دفعة
            </button>
            <button class="btn btn-info" onclick="showDeliveryModal()">
                <i class="fas fa-truck me-2"></i>تسجيل تسليم
            </button>
            <button class="btn btn-warning" onclick="showReturnModal()">
                <i class="fas fa-undo me-2"></i>تسجيل مرتجع
            </button>
            <button class="btn btn-secondary" onclick="refreshData()">
                <i class="fas fa-sync-alt me-2"></i>تحديث البيانات
            </button>
        </div>

        <!-- Transaction History -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>تاريخ الدفعات</h5>
                    </div>
                    <div class="card-body">
                        <div id="paymentHistory">
                            <?php if (!empty($financial_summary['payment_history'])): ?>
                                <?php foreach ($financial_summary['payment_history'] as $payment): ?>
                                <div class="transaction-item payment-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-money-bill-wave me-2"></i>
                                                دفعة بقيمة <?php echo number_format($payment['payment_amount'], 2); ?> جنيه
                                            </h6>
                                            <p class="mb-1">
                                                <small class="text-muted">
                                                    <?php echo date('Y-m-d H:i', strtotime($payment['payment_date'])); ?> | 
                                                    <?php echo $payment['payment_method']; ?> | 
                                                    <?php echo $payment['processed_by']; ?>
                                                </small>
                                            </p>
                                            <?php if (!empty($payment['notes'])): ?>
                                            <p class="mb-0"><small><?php echo htmlspecialchars($payment['notes']); ?></small></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <span class="amount-display amount-positive">
                                                +<?php echo number_format($payment['payment_amount'], 2); ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                متبقي: <?php echo number_format($payment['remaining_after_payment'], 2); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info alert-custom text-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    لا توجد دفعات مسجلة لهذه الشحنة
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-undo me-2"></i>سجل المرتجعات</h5>
                    </div>
                    <div class="card-body">
                        <div id="returnsHistory">
                            <?php if (!empty($financial_summary['returns'])): ?>
                                <?php foreach ($financial_summary['returns'] as $return): ?>
                                <div class="transaction-item return-item">
                                    <h6 class="mb-1">
                                        <i class="fas fa-undo me-2"></i>
                                        مرتجع بقيمة <?php echo number_format($return['returned_value'], 2); ?> جنيه
                                    </h6>
                                    <p class="mb-1">
                                        <small class="text-muted">
                                            <?php echo date('Y-m-d H:i', strtotime($return['return_date'])); ?> | 
                                            <?php echo $return['processed_by']; ?>
                                        </small>
                                    </p>
                                    <?php if (!empty($return['return_reason'])): ?>
                                    <p class="mb-0"><small>السبب: <?php echo htmlspecialchars($return['return_reason']); ?></small></p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info alert-custom text-center">
                                    <i class="fas fa-info-circle me-2"></i>
                                    لا توجد مرتجعات لهذه الشحنة
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- No parcel selected -->
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>ابحث عن شحنة لعرض تفاصيلها المالية</h4>
                <p class="text-muted">استخدم البحث أعلاه للعثور على الشحنة التي تريد إدارة دفعاتها</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>إضافة دفعة جديدة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <input type="hidden" id="paymentParcelId" value="<?php echo $parcel_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">مبلغ الدفعة (جنيه):</label>
                            <input type="number" class="form-control" id="paymentAmount" step="0.01" min="0" 
                                   max="<?php echo $parcel_data['remaining_balance'] ?? 0; ?>" required>
                            <small class="form-text text-muted">
                                الحد الأقصى: <?php echo number_format($parcel_data['remaining_balance'] ?? 0, 2); ?> جنيه
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">طريقة الدفع:</label>
                            <select class="form-select" id="paymentMethod" required>
                                <option value="cash">نقداً</option>
                                <option value="transfer">تحويل بنكي</option>
                                <option value="check">شيك</option>
                                <option value="online">دفع إلكتروني</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">رقم المرجع:</label>
                            <input type="text" class="form-control" id="paymentReference" placeholder="اختياري">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ملاحظات:</label>
                            <textarea class="form-control" id="paymentNotes" rows="3" placeholder="ملاحظات إضافية (اختياري)"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-success" onclick="submitPayment()">
                        <i class="fas fa-save me-2"></i>حفظ الدفعة
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delivery Modal -->
    <div class="modal fade" id="deliveryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-truck me-2"></i>تسجيل تسليم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="deliveryForm">
                        <input type="hidden" id="deliveryParcelId" value="<?php echo $parcel_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">قيمة التسليم (جنيه):</label>
                            <input type="number" class="form-control" id="deliveryValue" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ملاحظات التسليم:</label>
                            <textarea class="form-control" id="deliveryNotes" rows="3" placeholder="تفاصيل التسليم"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-info" onclick="submitDelivery()">
                        <i class="fas fa-save me-2"></i>تسجيل التسليم
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Return Modal -->
    <div class="modal fade" id="returnModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-undo me-2"></i>تسجيل مرتجع</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="returnForm">
                        <input type="hidden" id="returnParcelId" value="<?php echo $parcel_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">قيمة المرتجع (جنيه):</label>
                            <input type="number" class="form-control" id="returnValue" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">عدد القطع المرتجعة:</label>
                            <input type="number" class="form-control" id="returnItemsCount" min="1" value="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">سبب الإرجاع:</label>
                            <textarea class="form-control" id="returnReason" rows="3" placeholder="سبب إرجاع البضاعة" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-warning" onclick="submitReturn()">
                        <i class="fas fa-save me-2"></i>تسجيل المرتجع
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // البحث عن الشحنات
        function searchParcels() {
            const searchTerm = $('#searchTerm').val().trim();
            const searchType = $('#searchType').val();
            
            if (!searchTerm) {
                alert('يرجى إدخال مصطلح البحث');
                return;
            }
            
            $.post('enhanced_payment_ajax.php', {
                action: 'search_parcels',
                search_term: searchTerm,
                search_type: searchType
            }, function(response) {
                if (response.success) {
                    displaySearchResults(response.parcels);
                } else {
                    $('#searchResults').html(`<div class="alert alert-warning">${response.message}</div>`);
                }
            }, 'json');
        }
        
        // عرض نتائج البحث
        function displaySearchResults(parcels) {
            let html = '<h6 class="mt-3 mb-3">نتائج البحث:</h6>';
            
            if (parcels.length === 0) {
                html += '<div class="alert alert-info">لم يتم العثور على نتائج</div>';
            } else {
                html += '<div class="table-responsive"><table class="table table-striped">';
                html += '<thead><tr><th>رقم التتبع</th><th>المستلم</th><th>إجمالي الشحنة</th><th>المسلم</th><th>المدفوع</th><th>المتبقي</th><th>الحالة</th><th>إجراءات</th></tr></thead><tbody>';
                
                parcels.forEach(function(parcel) {
                    const statusClass = parcel.detailed_payment_status.replace('_', '-');
                    const statusText = {
                        'unpaid': 'غير مدفوعة',
                        'partially_paid': 'مدفوعة جزئياً',
                        'fully_paid': 'مدفوعة بالكامل'
                    }[parcel.detailed_payment_status] || 'غير محدد';
                    
                    html += `<tr>
                        <td>${parcel.tracking_number}</td>
                        <td>${parcel.recipient_name || '-'}</td>
                        <td>${parseFloat(parcel.total_shipment_value).toLocaleString('ar-EG', {minimumFractionDigits: 2})} ج.م</td>
                        <td>${parseFloat(parcel.delivered_value).toLocaleString('ar-EG', {minimumFractionDigits: 2})} ج.م</td>
                        <td>${parseFloat(parcel.amount_paid_so_far).toLocaleString('ar-EG', {minimumFractionDigits: 2})} ج.م</td>
                        <td>${parseFloat(parcel.remaining_balance).toLocaleString('ar-EG', {minimumFractionDigits: 2})} ج.م</td>
                        <td><span class="payment-status status-${statusClass}">${statusText}</span></td>
                        <td><a href="?parcel_id=${parcel.id}" class="btn btn-sm btn-primary">إدارة</a></td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
            }
            
            $('#searchResults').html(html);
        }
        
        // إظهار نافذة الدفع
        function showPaymentModal() {
            $('#paymentModal').modal('show');
        }
        
        // إظهار نافذة التسليم
        function showDeliveryModal() {
            $('#deliveryModal').modal('show');
        }
        
        // إظهار نافذة المرتجع
        function showReturnModal() {
            $('#returnModal').modal('show');
        }
        
        // إرسال الدفعة
        function submitPayment() {
            const formData = {
                action: 'add_payment',
                parcel_id: $('#paymentParcelId').val(),
                payment_amount: $('#paymentAmount').val(),
                payment_method: $('#paymentMethod').val(),
                reference_number: $('#paymentReference').val(),
                notes: $('#paymentNotes').val()
            };
            
            $.post('enhanced_payment_ajax.php', formData, function(response) {
                if (response.success) {
                    $('#paymentModal').modal('hide');
                    location.reload();
                } else {
                    alert('خطأ: ' + response.message);
                }
            }, 'json');
        }
        
        // إرسال التسليم
        function submitDelivery() {
            const formData = {
                action: 'record_delivery',
                parcel_id: $('#deliveryParcelId').val(),
                delivered_value: $('#deliveryValue').val(),
                delivery_notes: $('#deliveryNotes').val()
            };
            
            $.post('enhanced_payment_ajax.php', formData, function(response) {
                if (response.success) {
                    $('#deliveryModal').modal('hide');
                    location.reload();
                } else {
                    alert('خطأ: ' + response.message);
                }
            }, 'json');
        }
        
        // إرسال المرتجع
        function submitReturn() {
            const formData = {
                action: 'record_return',
                parcel_id: $('#returnParcelId').val(),
                return_value: $('#returnValue').val(),
                return_reason: $('#returnReason').val(),
                items_count: $('#returnItemsCount').val()
            };
            
            $.post('enhanced_payment_ajax.php', formData, function(response) {
                if (response.success) {
                    $('#returnModal').modal('hide');
                    location.reload();
                } else {
                    alert('خطأ: ' + response.message);
                }
            }, 'json');
        }
        
        // تحديث البيانات
        function refreshData() {
            location.reload();
        }
        
        // البحث عند الضغط على Enter
        $('#searchTerm').keypress(function(e) {
            if (e.which == 13) {
                searchParcels();
            }
        });
    </script>
</body>
</html>
