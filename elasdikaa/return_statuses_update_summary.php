<?php
/**
 * ملخص تحديث حالات المرتجعات
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث حالات المرتجعات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            transition: all 0.3s ease;
        }
        .status-card:hover {
            box-shadow: 0 4px 8px rgba(0,123,255,.1);
        }
        .status-branch {
            border-color: #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }
        .status-customer {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #a3d977 100%);
        }
        .workflow-step {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="text-center mb-5">
            <h1 class="display-4 text-success">
                <i class="fas fa-undo-alt"></i>
                تحديث حالات المرتجعات
            </h1>
            <p class="lead">تم تحديث النظام ليدعم التمييز بين المرتجعات في الفرع والمرتجعات المسلمة للعملاء</p>
            <div class="badge bg-success fs-6 p-3">
                <i class="fas fa-check-circle"></i> مكتمل بنجاح
            </div>
        </div>

        <!-- الحالات الجديدة -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h2><i class="fas fa-list"></i> الحالات المحدثة</h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- مرتجع للفرع -->
                            <div class="col-md-6">
                                <div class="status-card status-branch">
                                    <div class="text-center">
                                        <i class="fas fa-store text-warning" style="font-size: 3rem;"></i>
                                        <h3 class="mt-3 text-warning">مرتجع للفرع</h3>
                                        <div class="badge bg-warning text-dark fs-6 mb-3">ID: 9</div>
                                        <p><strong>الوصف:</strong> الشحنة رجعت للفرع (لم يتم تسليمها)</p>
                                        <p><strong>الاستخدام:</strong> عندما تفشل محاولة التسليم وترجع الشحنة للفرع</p>
                                        <div class="alert alert-warning">
                                            <strong>في انتظار:</strong> تسليم المرتجع للعميل
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- مرتجع للعميل -->
                            <div class="col-md-6">
                                <div class="status-card status-customer">
                                    <div class="text-center">
                                        <i class="fas fa-user-check text-success" style="font-size: 3rem;"></i>
                                        <h3 class="mt-3 text-success">مرتجع للعميل</h3>
                                        <div class="badge bg-success fs-6 mb-3">ID: 10</div>
                                        <p><strong>الوصف:</strong> تم تسليم المرتجع للعميل المرسل</p>
                                        <p><strong>الاستخدام:</strong> عندما يتم تسليم الشحنة المرتجعة للعميل الأصلي</p>
                                        <div class="alert alert-success">
                                            <strong>مكتملة:</strong> تم إنهاء دورة حياة الشحنة
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- سير العمل -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h2><i class="fas fa-route"></i> سير العمل للمرتجعات</h2>
                    </div>
                    <div class="card-body">
                        <div class="workflow-step">
                            <h5><i class="fas fa-play text-primary"></i> 1. بداية الشحنة</h5>
                            <p>الشحنة تبدأ بحالة "قيد التنفيذ" ثم تمر بمراحل التسليم العادية</p>
                        </div>

                        <div class="workflow-step">
                            <h5><i class="fas fa-times text-danger"></i> 2. فشل التسليم</h5>
                            <p>عند فشل محاولة التسليم (رفض المستلم، عدم وجود المستلم، إلخ)</p>
                            <p><strong>الإجراء:</strong> تغيير حالة الشحنة إلى <span class="badge bg-warning text-dark">"مرتجع للفرع"</span></p>
                        </div>

                        <div class="workflow-step">
                            <h5><i class="fas fa-hand-holding text-warning"></i> 3. في انتظار العميل</h5>
                            <p>الشحنة موجودة في الفرع بانتظار قدوم العميل لاستلامها</p>
                            <p><strong>الحالة:</strong> <span class="badge bg-warning text-dark">"مرتجع للفرع"</span></p>
                        </div>

                        <div class="workflow-step">
                            <h5><i class="fas fa-check-circle text-success"></i> 4. تسليم المرتجع</h5>
                            <p>عندما يأتي العميل ويستلم الشحنة المرتجعة</p>
                            <p><strong>الإجراء:</strong> تغيير حالة الشحنة إلى <span class="badge bg-success">"مرتجع للعميل"</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- التحديثات المطبقة -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h2><i class="fas fa-cogs"></i> التحديثات المطبقة</h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4><i class="fas fa-database text-primary"></i> قاعدة البيانات:</h4>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">✅ تحديث الحالة رقم 9 إلى "مرتجع للفرع"</li>
                                    <li class="list-group-item">✅ إضافة حالة جديدة "مرتجع للعميل" (ID: 10)</li>
                                    <li class="list-group-item">✅ تحديث جدول parcel_status</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h4><i class="fas fa-code text-info"></i> ملفات النظام:</h4>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">✅ تحديث customer_profile_ajax.php</li>
                                    <li class="list-group-item">✅ تحديث customer_profile.php</li>
                                    <li class="list-group-item">✅ إضافة عمود "حالة المرتجع" في تاب المرتجعات</li>
                                    <li class="list-group-item">✅ تحديث قائمة فلتر الحالات</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- تاب المرتجعات المحدث -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h2><i class="fas fa-table"></i> تاب المرتجعات المحدث</h2>
                    </div>
                    <div class="card-body">
                        <p>تم تحديث تاب المرتجعات في ملف العميل ليشمل:</p>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>رقم التتبع</th>
                                        <th>المستلم</th>
                                        <th class="text-warning">حالة المرتجع ⭐</th>
                                        <th>سبب الإرجاع</th>
                                        <th>مبلغ COD</th>
                                        <th>تاريخ الإرجاع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>TRACK-001</td>
                                        <td>أحمد محمد</td>
                                        <td><span class="badge bg-warning text-dark">في الفرع</span></td>
                                        <td>الشحنة في الفرع (لم يتم تسليمها)</td>
                                        <td>500.00 جنيه</td>
                                        <td>2025-01-08</td>
                                    </tr>
                                    <tr>
                                        <td>TRACK-002</td>
                                        <td>سارة أحمد</td>
                                        <td><span class="badge bg-success">مسلم للعميل</span></td>
                                        <td>تم تسليم المرتجع للعميل</td>
                                        <td>750.00 جنيه</td>
                                        <td>2025-01-07</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">⭐ العمود الجديد المضاف</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- كيفية الاستخدام -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h2><i class="fas fa-play-circle"></i> كيفية الاستخدام</h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4>📦 للشحنة التي لم يتم تسليمها:</h4>
                                <ol>
                                    <li>اذهب إلى قائمة الشحنات</li>
                                    <li>ابحث عن الشحنة المطلوبة</li>
                                    <li>غير حالتها إلى <strong>"مرتجع للفرع"</strong></li>
                                    <li>ستظهر في تاب المرتجعات كـ <span class="badge bg-warning text-dark">في الفرع</span></li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h4>✅ عند تسليم المرتجع للعميل:</h4>
                                <ol>
                                    <li>ابحث عن الشحنة في قائمة الشحنات</li>
                                    <li>غير حالتها من "مرتجع للفرع" إلى <strong>"مرتجع للعميل"</strong></li>
                                    <li>ستظهر في تاب المرتجعات كـ <span class="badge bg-success">مسلم للعميل</span></li>
                                    <li>تكون دورة حياة الشحنة مكتملة</li>
                                </ol>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <h5><i class="fas fa-lightbulb"></i> نصائح:</h5>
                            <ul>
                                <li>استخدم فلتر الحالات لعرض المرتجعات فقط</li>
                                <li>راقب تاب المرتجعات في ملف العميل</li>
                                <li>الحالات تظهر بألوان مختلفة للتمييز السهل</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- الخلاصة -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <div class="alert alert-success p-4">
                    <h2 class="display-5 text-success">
                        <i class="fas fa-check-double"></i> تم التحديث بنجاح!
                    </h2>
                    <h3>الآن يمكنك التمييز بين المرتجعات بوضوح</h3>
                    <p class="fs-5">النظام يدعم تتبع كامل لدورة حياة المرتجعات من الفرع إلى العميل</p>
                    
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <a href="customer_list.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-users"></i> قائمة العملاء
                        </a>
                        <a href="parcel_list.php" class="btn btn-success btn-lg">
                            <i class="fas fa-boxes"></i> قائمة الشحنات
                        </a>
                    </div>
                    
                    <div class="mt-4">
                        <div class="badge bg-warning fs-6 p-3 mx-2">
                            <i class="fas fa-store"></i> مرتجع للفرع
                        </div>
                        <div class="badge bg-success fs-6 p-3 mx-2">
                            <i class="fas fa-user-check"></i> مرتجع للعميل
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
