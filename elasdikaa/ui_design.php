<?php
/**
 * ========================================
 * تصميم الواجهات الموحدة لنظام الدفع الجزئي
 * ========================================
 * 
 * تاريخ الإنشاء: 2025-01-11
 * الإصدار: 1.0.0
 * المطور: نظام الشحن الموحد
 * 
 * هذا الملف يحتوي على تصميم الواجهات الموحدة
 * للمندوب والأدمن مع نفس التصميم والألوان
 */

/**
 * ========================================
 * 1. مودال الدفع الجزئي الموحد
 * ========================================
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تصميم الواجهات - نظام الدفع الجزئي الموحد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* الألوان الموحدة للنظام */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            
            --border-radius: 8px;
            --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        /* تصميم موحد للبطاقات */
        .unified-card {
            border: 1px solid #e9ecef;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            background: white;
        }
        
        .unified-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        /* تصميم موحد للأزرار */
        .unified-btn {
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
            padding: 8px 20px;
        }
        
        .unified-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        /* تصميم موحد للحقول */
        .unified-form-control {
            border-radius: var(--border-radius);
            border: 2px solid #e9ecef;
            transition: var(--transition);
        }
        
        .unified-form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        /* تصميم موحد للجداول */
        .unified-table {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .unified-table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        /* تصميم موحد للتنبيهات */
        .unified-alert {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--box-shadow);
        }
        
        /* تصميم موحد للمودال */
        .unified-modal .modal-content {
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .unified-modal .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        
        /* تصميم موحد للقوائم */
        .unified-list-group {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .unified-list-group .list-group-item {
            border: none;
            border-bottom: 1px solid #e9eced;
        }
        
        .unified-list-group .list-group-item:last-child {
            border-bottom: none;
        }
        
        /* تصميم موحد للشريط الجانبي */
        .unified-sidebar {
            background: var(--dark-color);
            color: white;
            min-height: 100vh;
        }
        
        .unified-sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            transition: var(--transition);
            border-radius: var(--border-radius);
            margin: 2px 0;
        }
        
        .unified-sidebar .nav-link:hover,
        .unified-sidebar .nav-link.active {
            color: white;
            background: var(--primary-color);
        }
        
        /* تصميم موحد للشريط العلوي */
        .unified-navbar {
            background: white;
            box-shadow: var(--box-shadow);
            border: none;
        }
        
        .unified-navbar .navbar-brand {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        /* تصميم موحد للشريط السفلي */
        .unified-footer {
            background: var(--dark-color);
            color: white;
            padding: 20px 0;
            margin-top: 50px;
        }
        
        /* تصميم موحد للبطاقات الإحصائية */
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 10px 0;
            box-shadow: var(--box-shadow);
        }
        
        .stats-card .stats-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .stats-card .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .stats-card .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        /* تصميم موحد للرسوم البيانية */
        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin: 20px 0;
        }
        
        /* تصميم موحد للنماذج */
        .form-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin: 20px 0;
            box-shadow: var(--box-shadow);
        }
        
        .form-section .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        /* تصميم موحد للتنبيهات */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* تصميم موحد للقوائم المنسدلة */
        .unified-dropdown .dropdown-menu {
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: none;
            padding: 10px 0;
        }
        
        .unified-dropdown .dropdown-item {
            padding: 8px 20px;
            transition: var(--transition);
        }
        
        .unified-dropdown .dropdown-item:hover {
            background: var(--light-color);
            color: var(--primary-color);
        }
        
        /* تصميم موحد للتبويبات */
        .unified-tabs .nav-tabs {
            border: none;
            border-bottom: 2px solid #e9ecef;
        }
        
        .unified-tabs .nav-tabs .nav-link {
            border: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            margin-right: 5px;
            color: var(--secondary-color);
            transition: var(--transition);
        }
        
        .unified-tabs .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            border: none;
        }
        
        /* تصميم موحد للصفحات */
        .unified-pagination .page-link {
            border: none;
            color: var(--primary-color);
            margin: 0 2px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .unified-pagination .page-link:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .unified-pagination .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* تصميم موحد للبحث */
        .search-container {
            position: relative;
            margin: 20px 0;
        }
        
        .search-container .search-input {
            padding: 12px 45px 12px 20px;
            border-radius: 25px;
            border: 2px solid #e9ecef;
            width: 100%;
            transition: var(--transition);
        }
        
        .search-container .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        
        .search-container .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
        }
        
        /* تصميم موحد للفلترة */
        .filter-container {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 20px 0;
        }
        
        .filter-container .filter-title {
            color: var(--dark-color);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        /* تصميم موحد للطباعة */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            .unified-card {
                box-shadow: none;
                border: 1px solid #000;
            }
        }
        
        /* تصميم موحد للأجهزة المحمولة */
        @media (max-width: 768px) {
            .unified-card {
                margin: 10px 0;
            }
            
            .stats-card {
                text-align: center;
            }
            
            .form-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- الشريط الجانبي -->
            <div class="col-md-3 col-lg-2">
                <div class="unified-sidebar p-3">
                    <h5 class="text-center mb-4">نظام الدفع الجزئي</h5>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="#dashboard">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            لوحة التحكم
                        </a>
                        <a class="nav-link" href="#payments">
                            <i class="fas fa-credit-card me-2"></i>
                            الدفعات
                        </a>
                        <a class="nav-link" href="#shipments">
                            <i class="fas fa-shipping-fast me-2"></i>
                            الشحنات
                        </a>
                        <a class="nav-link" href="#reports">
                            <i class="fas fa-chart-bar me-2"></i>
                            التقارير
                        </a>
                        <a class="nav-link" href="#settings">
                            <i class="fas fa-cog me-2"></i>
                            الإعدادات
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- المحتوى الرئيسي -->
            <div class="col-md-9 col-lg-10">
                <!-- الشريط العلوي -->
                <nav class="navbar navbar-expand-lg unified-navbar mb-4">
                    <div class="container-fluid">
                        <a class="navbar-brand" href="#">
                            <i class="fas fa-shipping-fast me-2"></i>
                            نظام الشحن الموحد
                        </a>
                        
                        <div class="navbar-nav ml-auto">
                            <div class="nav-item dropdown unified-dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-toggle="dropdown">
                                    <i class="fas fa-user me-2"></i>
                                    المستخدم
                                    <span class="notification-badge">3</span>
                                </a>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="#profile">
                                        <i class="fas fa-user-circle me-2"></i>
                                        الملف الشخصي
                                    </a>
                                    <a class="dropdown-item" href="#settings">
                                        <i class="fas fa-cog me-2"></i>
                                        الإعدادات
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="#logout">
                                        <i class="fas fa-sign-out-alt me-2"></i>
                                        تسجيل الخروج
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <!-- المحتوى -->
                <div class="container-fluid">
                    <!-- البطاقات الإحصائية -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="stats-number">1,234</div>
                                <div class="stats-label">إجمالي الدفعات</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon">
                                    <i class="fas fa-shipping-fast"></i>
                                </div>
                                <div class="stats-number">5,678</div>
                                <div class="stats-label">الشحنات النشطة</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stats-number">45,678</div>
                                <div class="stats-label">إجمالي المبالغ</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stats-number">89</div>
                                <div class="stats-label">المستخدمين النشطين</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- نموذج الدفع الجزئي -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-credit-card me-2"></i>
                            تسجيل دفعة جزئية جديدة
                        </h4>
                        
                        <form id="partialPaymentForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="trackingNumber">رقم التتبع</label>
                                        <input type="text" class="form-control unified-form-control" id="trackingNumber" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paymentAmount">مبلغ الدفعة</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control unified-form-control" id="paymentAmount" step="0.01" min="0.01" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text">ج.م</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paymentMethod">طريقة الدفع</label>
                                        <select class="form-control unified-form-control" id="paymentMethod" required>
                                            <option value="">اختر طريقة الدفع</option>
                                            <option value="cash">نقداً</option>
                                            <option value="transfer">تحويل بنكي</option>
                                            <option value="check">شيك</option>
                                            <option value="online">دفع إلكتروني</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paymentDate">تاريخ الدفع</label>
                                        <input type="date" class="form-control unified-form-control" id="paymentDate" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="paymentNote">ملاحظة الدفع</label>
                                <textarea class="form-control unified-form-control" id="paymentNote" rows="3" placeholder="أدخل ملاحظة حول الدفعة..."></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary unified-btn">
                                    <i class="fas fa-save me-2"></i>
                                    حفظ الدفعة
                                </button>
                                <button type="reset" class="btn btn-secondary unified-btn ml-2">
                                    <i class="fas fa-undo me-2"></i>
                                    إعادة تعيين
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- جدول الدفعات -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-list me-2"></i>
                            تاريخ الدفعات
                        </h4>
                        
                        <!-- فلترة وبحث -->
                        <div class="filter-container">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="search-container">
                                        <input type="text" class="search-input" placeholder="البحث في الدفعات...">
                                        <i class="fas fa-search search-icon"></i>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-control unified-form-control">
                                        <option value="">جميع طرق الدفع</option>
                                        <option value="cash">نقداً</option>
                                        <option value="transfer">تحويل بنكي</option>
                                        <option value="check">شيك</option>
                                        <option value="online">دفع إلكتروني</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="date" class="form-control unified-form-control">
                                </div>
                            </div>
                        </div>
                        
                        <!-- الجدول -->
                        <div class="table-responsive">
                            <table class="table table-striped unified-table">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>رقم التتبع</th>
                                        <th>المبلغ</th>
                                        <th>طريقة الدفع</th>
                                        <th>الملاحظة</th>
                                        <th>من قام بالتحصيل</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>2025-01-11</td>
                                        <td>TRACK-123456</td>
                                        <td>500.00 ج.م</td>
                                        <td><span class="badge badge-success">نقداً</span></td>
                                        <td>دفعة جزئية</td>
                                        <td>أحمد محمد</td>
                                        <td>
                                            <button class="btn btn-sm btn-info unified-btn">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning unified-btn">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger unified-btn">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>2025-01-10</td>
                                        <td>TRACK-123457</td>
                                        <td>750.00 ج.م</td>
                                        <td><span class="badge badge-info">تحويل بنكي</span></td>
                                        <td>دفعة كاملة</td>
                                        <td>محمد علي</td>
                                        <td>
                                            <button class="btn btn-sm btn-info unified-btn">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning unified-btn">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger unified-btn">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- الصفحات -->
                        <nav aria-label="صفحات الدفعات">
                            <ul class="pagination justify-content-center unified-pagination">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" tabindex="-1">السابق</a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#">التالي</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                
                <!-- الشريط السفلي -->
                <footer class="unified-footer text-center">
                    <div class="container">
                        <p>&copy; 2025 نظام الشحن الموحد. جميع الحقوق محفوظة.</p>
                    </div>
                </footer>
            </div>
        </div>
    </div>
    
    <!-- مودال الدفع الجزئي -->
    <div class="modal fade unified-modal" id="partialPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-credit-card me-2"></i>
                        تسجيل دفعة جزئية
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- معلومات الشحنة -->
                    <div class="unified-card p-3 mb-3">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            معلومات الشحنة
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>رقم التتبع:</strong>
                                    <span id="modalTrackingNumber">TRACK-123456</span>
                                </div>
                                <div class="mb-2">
                                    <strong>المبلغ المطلوب:</strong>
                                    <span id="modalTotalAmount">2,500.00 ج.م</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>المدفوع سابقاً:</strong>
                                    <span id="modalPaidAmount">0.00 ج.م</span>
                                </div>
                                <div class="mb-2">
                                    <strong>المتبقي:</strong>
                                    <span id="modalRemainingAmount" class="text-danger">2,500.00 ج.م</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- إدخال الدفعة -->
                    <div class="unified-card p-3 mb-3">
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-edit me-2"></i>
                            إدخال الدفعة الجديدة
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modalPaymentAmount">المبلغ المدفوع</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control unified-form-control" id="modalPaymentAmount" step="0.01" min="0.01" placeholder="أدخل المبلغ">
                                        <div class="input-group-append">
                                            <span class="input-group-text">ج.م</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="modalPaymentMethod">طريقة الدفع</label>
                                    <select class="form-control unified-form-control" id="modalPaymentMethod">
                                        <option value="cash">نقداً</option>
                                        <option value="transfer">تحويل بنكي</option>
                                        <option value="check">شيك</option>
                                        <option value="online">دفع إلكتروني</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="modalPaymentNote">ملاحظة الدفع</label>
                            <textarea class="form-control unified-form-control" id="modalPaymentNote" rows="3" placeholder="أدخل ملاحظة حول الدفعة..."></textarea>
                        </div>
                    </div>
                    
                    <!-- ملخص الدفعة -->
                    <div class="unified-card p-3" id="paymentSummary" style="display: none;">
                        <h6 class="text-success mb-3">
                            <i class="fas fa-calculator me-2"></i>
                            ملخص الدفعة
                        </h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="text-success font-weight-bold" id="summaryNewAmount">0.00 ج.م</div>
                                    <small class="text-muted">المبلغ الجديد</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="text-info font-weight-bold" id="summaryTotalPaid">0.00 ج.م</div>
                                    <small class="text-muted">إجمالي المدفوع</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="text-warning font-weight-bold" id="summaryFinalRemaining">0.00 ج.م</div>
                                    <small class="text-muted">المتبقي بعد الدفع</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary unified-btn" data-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        إلغاء
                    </button>
                    <button type="button" class="btn btn-primary unified-btn" id="savePayment" disabled>
                        <i class="fas fa-save me-2"></i>
                        حفظ الدفعة
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // كود JavaScript للواجهة
        $(document).ready(function() {
            // معالج إدخال المبلغ
            $('#modalPaymentAmount').on('input', function() {
                var amount = parseFloat($(this).val()) || 0;
                var remaining = parseFloat($('#modalRemainingAmount').text().replace(/[^\d.-]/g, '')) || 0;
                
                if (amount > 0 && amount <= remaining) {
                    var currentPaid = parseFloat($('#modalPaidAmount').text().replace(/[^\d.-]/g, '')) || 0;
                    var newTotalPaid = currentPaid + amount;
                    var newRemaining = remaining - amount;
                    
                    $('#summaryNewAmount').text(amount.toFixed(2) + ' ج.م');
                    $('#summaryTotalPaid').text(newTotalPaid.toFixed(2) + ' ج.م');
                    $('#summaryFinalRemaining').text(newRemaining.toFixed(2) + ' ج.م');
                    
                    $('#paymentSummary').show();
                    $('#savePayment').prop('disabled', false);
                } else {
                    $('#paymentSummary').hide();
                    $('#savePayment').prop('disabled', true);
                }
            });
            
            // معالج حفظ الدفعة
            $('#savePayment').on('click', function() {
                // هنا يتم إرسال البيانات للخادم
                alert('تم حفظ الدفعة بنجاح!');
                $('#partialPaymentModal').modal('hide');
            });
            
            // معالج النموذج
            $('#partialPaymentForm').on('submit', function(e) {
                e.preventDefault();
                // هنا يتم إرسال البيانات للخادم
                alert('تم حفظ الدفعة بنجاح!');
            });
        });
    </script>
</body>
</html>


