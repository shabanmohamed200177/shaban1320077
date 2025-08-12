<?php
include 'db_connect.php';

// حساب الرصيد الحالي
$res = mysqli_query($conn, "SELECT SUM(IF(direction='in', amount, -amount)) AS balance FROM cashbox_transactions");
$row = mysqli_fetch_assoc($res);
$balance = $row['balance'] ?? 0;

// جلب آخر الحركات المالية مع تفاصيل الطرف
$latest = mysqli_query($conn, "
    SELECT t.id, t.type, t.relation_id, t.amount, t.direction, t.notes, t.created_at,
        CASE 
            WHEN t.type='customer' THEN (SELECT name FROM customers WHERE id=t.relation_id)
            WHEN t.type='agent' THEN (SELECT company_name FROM agents WHERE id=t.relation_id)
            WHEN t.type='courier' THEN (SELECT name FROM couriers WHERE id=t.relation_id)
            ELSE 'الخزنة' 
        END AS party_name
    FROM cashbox_transactions t
    ORDER BY t.created_at DESC 
    LIMIT 10
");
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header text-end bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fa-solid fa-vault"></i> الخزنة الرئيسية</h4>
            <span class="fs-5 text-light">
                <i class="fa-solid fa-money-bill-wave"></i> الرصيد الحالي: 
                <span class="text-success"><?= number_format($balance, 2) ?> جنيه</span>
            </span>
        </div>
        <div class="card-body">
            <hr>
            <h6><i class="fa-solid fa-list"></i> آخر الحركات المالية:</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-striped text-end align-middle">
                    <thead class="table-info">
                        <tr>
                            <th>المعرف</th>
                            <th>النوع</th>
                            <th>اسم الطرف</th>
                            <th>المبلغ</th>
                            <th>الاتجاه</th>
                            <th>ملاحظات</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($latest)): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td>
                                <?php
                                if($row['type']=='customer') echo '<span class="badge bg-success">عميل</span>';
                                elseif($row['type']=='agent') echo '<span class="badge bg-primary">وكيل</span>';
                                elseif($row['type']=='courier') echo '<span class="badge bg-warning text-dark">مندوب</span>';
                                elseif($row['type']=='project') echo '<span class="badge bg-info text-dark">مشروع</span>';
                                else echo htmlspecialchars($row['type']);
                                ?>
                            </td>
                            <td><?= $row['party_name'] ?: '-' ?></td>
                            <td><?= number_format($row['amount'],2) ?> جنيه</td>
                            <td>
                                <?= $row['direction']=='in' ? 
                                    '<span class="badge bg-success">داخل</span>' : 
                                    '<span class="badge bg-danger">خارج</span>' ?>
                            </td>
                            <td><?= htmlspecialchars($row['notes']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(mysqli_num_rows($latest)==0): ?>
                        <tr>
                            <td colspan="7" class="text-center">لا يوجد حركات مالية</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <a href="index.php?page=transactions" class="btn btn-info"><i class="fa-solid fa-list"></i> عرض كل الحركات المالية</a>
        </div>
    </div>
</div>

<!-- روابط أيقونات fontawesome لو لم تكن مضافة -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">